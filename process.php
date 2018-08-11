<?php

$PYTHON_SERVER = "http://localhost:5000/";

$code = 200;
$message = 'Success.';
header('Content-Type: application/json');

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

    $vid_id = extract_vid_id($data);
    $response = call_get_api($PYTHON_SERVER . "add", array("vid" => $vid_id));

    return array_key_exists('message', $response) &&
           $response['message'] === 'Success!';
}

function queue_control($control) {
    // Make the control lower case to avoid ambiguity.
    $control = strtolower($control);
    switch ($control) {
        case 'skip':
        case 'pause':
        case 'resume':
            global $PYTHON_SERVER;

            call_get_api($PYTHON_SERVER . $control);
            return true;
        default:
            $code = 400;
            $message = 'Unknown action $control.';
            return false;
    }
}

$create = !file_exists('farther.db');
$pdo = new PDO('sqlite:farther.db');

function nearer_record($v, $note) {
    global $pdo;
    global $create;

    if ($create) {
        $pdo->exec(
<<<EOF
CREATE TABLE `history` (
  `user` varchar(64) NOT NULL,
  `v` varchar(16) NOT NULL,
  `created` datetime NOT NULL,
  `note` varchar(255) NULL
)
EOF
        );
    }

    $result = $pdo->prepare(
<<<EOF
INSERT INTO `history` (
  `user`,
  `v`,
  `created`,
  `note`
)
VALUES (
  :user,
  :v,
  DATETIME('now'),
  :note
)
EOF
    );

    $result->execute(array(
        ':user' => $_POST['user'],
        ':v' => $v,
        ':note' => $note
    ));
}


if (array_key_exists('action', $_GET)) { // Queue control action.
    queue_control($_GET['action']);
}

if (array_key_exists('url', $_POST)) { // Add song to queue.
    // Get the video id and video data.
    $vid_id = extract_vid_id($_POST['url']);
    $data = get_vid_data($vid_id);

    // Simple Ride filter.
    if (strpos(strtolower($data->title), 'valkyries') === false) {
        // Get the POST data.
        if (add_vid_to_queue($_POST['url'])) {
            // Record in the database.
            nearer_record('PLAY ' . $vid_id, substr($_POST['note'], 0, 255));
            $message = 'Successfully added video to queue.';
        } else {
            $code = 500; // Server error.
            $message = 'Failed to add video to queue.';
        }
    } else {
      $code = 401; // You can't just play the ride!
      $message = 'Ride detected. Nice try, punk.';
    }
}

if (array_key_exists('status', $_GET)) {
    $result = $pdo->prepare(
<<<EOF
SELECT *
FROM `history`
WHERE `v` LIKE 'PLAY %'
ORDER BY `created` DESC
LIMIT 20
EOF
      );

    $result->execute();

    $songs = array();
    $i = 0;

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $vid = substr($row['v'], 5);
        $url = 'http://www.youtube.com/watch?v=' . $vid;
        $data = get_vid_data($vid);
        $title = htmlentities($data->title, null, 'UTF-8');
        $author_name = htmlentities($data->author_name, null, 'UTF-8');
        $author_url = htmlentities($data->author_url, null, 'UTF-8');
        $thumbnail = htmlentities($data->thumbnail_url, null, 'UTF-8');
        $note = htmlentities($row['note'], null, 'UTF-8');

	$song = array(
            'url' => $url,
            'title' => $title,
            'author_name' => $author_name,
            'author_url' => $author_url,
            'thumbnail' => $thumbnail,
            'note' => $note,
            'added_by' => $row['user'],
            'added_on' => $row['created']
        );
        $songs[$i++] = $song;
    }

    $data = call_get_api($PYTHON_SERVER . 'status');
    unset($data['queue']);
    //unset($data['status']);

    if ($data['current'] != null) {
	$url = 'http://www.youtube.com/watch?v=' . $data['current'];
	$curr_data = get_vid_data($data['current']);
	$title = htmlentities($curr_data->title, null, 'UTF-8');
	$author_name = htmlentities($curr_data->author_name, null, 'UTF-8');
	$author_url = htmlentities($curr_data->author_url, null, 'UTF-8');
	$thumbnail = htmlentities($curr_data->thumbnail_url, null, 'UTF-8');

	$current = array(
	'url' => $url,
	'title' => $title,
	'author_name' => $author_name,
	'author_url' => $author_url,
	'thumbnail' => $thumbnail
	);
	$data['current'] = $current;
    }

    $data['songs'] = $songs;

    echo json_encode($data);
} else {
    $result = $code == 200 ? 'success' : 'failure';
    echo "{\"result\": \"$result\", \"message\": \"$message\"}";
    http_response_code($code);
}

?>
