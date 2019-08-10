<?php

require_once("check_ip.php");

if (! valid_ip($_SERVER['REMOTE_ADDR'])) {
    echo "{\"message\": \"You must be on the Caltech network to use Farther.\"}";
    http_response_code(403);
    exit();
}


$PYTHON_SERVER = "http://localhost:27036/";

$code = 200;
$message = 'Success.';
header('Content-Type: application/json');

$userfile = file_get_contents("allowed_names.txt");
$rows = explode("\n", $userfile);
$usermap = array();
foreach($rows as $data) {
    //get row data
    $row_data = explode('	', $data);
    $usermap[$row_data[0]] = $row_data[1];
}

// Method: POST, PUT, GET etc
// Data: array("param" => "value") ==> index.php?param=value
// Based on: https://stackoverflow.com/questions/9802788/
function call_get_api($url, $data = false) {
    if ($data)
        $url = sprintf("%s?%s", $url, http_build_query($data));

    // Make the api call.
    $result = file_get_contents($url);

    // Decode the JSON response.
    $result = json_decode($result, true);

    return $result;
}

function extract_vid_id($data) {
    if (preg_match_all("/#[\w-]{11}#/", $data) === 1) {
        // Data is a video id.
        return $data;
    }

    // Parse the given URL to get the query string.
    $parsed = parse_url($data);

    if ($parsed['host'] === 'youtu.be') {
        return (substr($parsed['path'], 1));
    }

    // Parse the query string into an array.
    parse_str($parsed['query'], $query);

    // Return the 'v' key of the array.
    return $query['v'];
}

function get_vid_data($v) {
    $url = 'http://www.youtube.com/watch?v=' . $v;
    return json_decode(file_get_contents("http://www.youtube.com/oembed?url=$url&format=json"));
}

function add_vid_to_queue($data) {
    global $PYTHON_SERVER;
    global $usermap;

    $vid_id = extract_vid_id($data);
    $response = call_get_api(
        $PYTHON_SERVER . "add",
        array(
            "vid" => $vid_id,
            "user" => $usermap[$_POST['user']],
            "note" => $_POST['note']
        )
    );

    return array_key_exists('message', $response) &&
           $response['message'] === 'Success!';
}

function queue_control($data) {
    // TODO uncomment during interhouse
    //$code = 401;
    //$message = 'no controls during interhorse';
    //return false;

    // Make the control lower case to avoid ambiguity.
    $control = strtolower($control);
    switch ($control) {
        case 'skip':
        case 'pause':
        case 'resume':
            global $PYTHON_SERVER;
            global $usermap;

            $response = call_get_api(
                $PYTHON_SERVER . $control,
                array(
                    "user" => $usermap[$_POST['user']],
                    "note" => $_POST['note']
                )
            );

            return array_key_exists('message', $response) &&
                   $response['message'] === 'Success!';
        default:
            $code = 400;
            $message = 'Unknown action $control.';
            return false;
    }
}

function vid_data($vid_id) {
    $url = 'http://www.youtube.com/watch?v=' . $vid_id;
    $curr_data = get_vid_data($vid_id);
    $title = htmlentities($curr_data->title, null, 'UTF-8');
    $author_name = htmlentities($curr_data->author_name, null, 'UTF-8');
    $author_url = htmlentities($curr_data->author_url, null, 'UTF-8');
    $thumbnail = htmlentities($curr_data->thumbnail_url, null, 'UTF-8');

    $vid_data = array(
        'url' => $url,
        'title' => $title,
        'author_name' => $author_name,
        'author_url' => $author_url,
        'thumbnail' => $thumbnail
    );
    return $vid_data;
}

if (array_key_exists('action', $_POST)) { // Queue control action.
    if (! array_key_exists($_POST['user'], $usermap)) {
        $code = 401;
        $message = 'User not found.';
    } else {
        if ( queue_control($_POST['action']) ) {
            $message = "Action performed.";
        } else {
            $code = 500; // Server error.
            $message = 'Failed to perform player action.';
        }
    }
}

if (array_key_exists('url', $_POST)) { // Add song to queue.
    // Get the video id and video data.
    $vid_id = extract_vid_id($_POST['url']);
    $data = get_vid_data($vid_id);

    $RIDE_KEYWORDS = array('valkyries', 'beep beep lettuce', 'ROTV');

    // Simple Ride filter.
    $ride = false;
    foreach ($RIDE_KEYWORDS as $keyword) {
        if (strpos(strtolower($data->title), $keyword) !== false) {
            $ride = true;
            break;
        }
    }

    if ($ride) {
        $code = 403; // You can't just play the ride!
        $message = 'Ride detected. Nice try, punk.';
    } else if (! array_key_exists($_POST['user'], $usermap)) {
        $code = 401;
        $message = 'User not found.';
    } else {
        // Get the POST data.
        if (add_vid_to_queue($_POST['url'])) {
            $message = 'Successfully added video to queue.';
        } else {
            $code = 500; // Server error.
            $message = 'Failed to add video to queue.';
        }
    }
}

if (array_key_exists('status', $_GET)) {
    $history = array();
    $queue = array();
    $songs = array();

    $data = call_get_api($PYTHON_SERVER . 'status');
    //unset($data['queue']);
    //unset($data['status']);

    if ($data['current'] != null) {
        $current = vid_data($data['current']['vid']);
        // TODO update to send actions

        $data['current'] = $current;
        array_push($songs, $current);
    }

    echo json_encode($data);
} else {
    $result = $code == 200 ? 'success' : 'failure';
    echo "{\"result\": \"$result\", \"message\": \"$message\"}";
    http_response_code($code);
}

?>
