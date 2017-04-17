<?
include(__DIR__ . '/../lib/include.php');

$sock = 'unix:///srv/python/nearer/.nearer';
$play = @file_get_contents('/srv/ftp/nearer');

function nearer_control($message) {
  global $sock;

  $fp = fsockopen($sock);

  if (!$fp) {
    return false;
  }

  fwrite($fp, $message . "\n");
  fclose($fp);
  return true;
}

function nearer_record($v, $note) {
  global $pdo;

  $result = $pdo->prepare(<<<EOF
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
    ':user' => $_SERVER['PHP_AUTH_USER'],
    ':v' => $v,
    ':note' => $note
  ));
}

function get_data($url) {
  return json_decode(file_get_contents("http://www.youtube.com/oembed?url=$url&format=json"));
}

function get_length($url) {
  if (preg_match('/([\'"])length_seconds\1:([\'"]?)(\d+)\2/', file_get_contents($url), $matches)) {
    return $matches[3];
  }
}

function get_url($v, $embed = false) {
  return $embed ? 'http://www.youtube.com/embed/' . $v : 'http://www.youtube.com/watch?v=' . $v;
}

$create = !file_exists('nearer.db');
$pdo = new PDO('sqlite:nearer.db');
$error = false;
$success = false;

if ($create) {
  $pdo->exec(<<<EOF
CREATE TABLE `history` (
  `user` varchar(64) NOT NULL,
  `v` varchar(16) NOT NULL,
  `created` datetime NOT NULL,
  `note` varchar(255) NULL
)
EOF
    );
}

if (array_key_exists('action', $_GET)) {
  $action = strtoupper($_GET['action']);

  switch ($action) {
    case 'SKIP':
    case 'STOP':
      nearer_record($action . ' ' . substr($play, -11), NULL);
    case 'PLAY':
      nearer_control($action);
      break;
  }
}

if (array_key_exists('url', $_POST)) {
  if (preg_match('/[\w-]{11}/', $_POST['url'], $matches) and $length = get_length(get_url($matches[0]), true)) {
    $data = get_data(get_url($matches[0]));

    if (strpos(strtolower($data->title), 'valkyries') === false) {
      if (nearer_control("APPEND $matches[0] $length")) {
        nearer_record('PLAY ' . $matches[0], substr($_POST['note'], 0, 255));
        $success = 'Successfully added video to queue.';
      } else {
        $error = 'Failed to add video to queue.';
      }
    } else {
      $error = 'Ride detected. Nice try, punk.';
    }
  } else {
    $error = 'Invalid URL or video ID.';
  }
}
?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
<?
print_head('Nearer');
?>  </head>
  <body>
    <div id="main">
      <h1>Nearer</h1>
<?
if ($error) {
  echo <<<EOF
      <div class="error">$error</div>

EOF;
}

if ($success) {
  echo <<<EOF
      <div class="success">$success</div>

EOF;
}

$subtitles = array(
  'Beats Ricketts Music',
  "Don't Play the Ride",
  'Play Loud, Play Proud',
  'The Day the Music Died'
);

$subtitle = $subtitles[mt_rand(0, count($subtitles) - 1)];

echo <<<EOF
      <h2>$subtitle</h2>

EOF;
?>      <p>Music controls may take up to ten seconds to take effect.</p>
      <form action="./" method="post">
        <div class="form-control">
          <label for="url">YouTube URL</label>
          <div class="input-group">
            <input type="text" id="url" name="url" />
          </div>
        </div>
        <div class="form-control optional">
          <label for="note">Note</label>
          <div class="input-group">
            <input type="text" id="note" name="note" maxlength="255" />
          </div>
        </div>
        <div class="form-control">
          <div class="input-group">
            <div class="pull-right">
              <a class="btn" href="?action=play">&nbsp;&#9654;&nbsp;</a>
              <a class="btn" href="?action=skip">&nbsp;&#9197;&nbsp;</a>
              <a class="btn" href="?action=stop">&nbsp;&#9724;&nbsp;</a>
            </div>
            <input type="submit" value="Submit" />
          </div>
        </div>
      </form>
      <h2>Recently Added</h2>
<?
$result = $pdo->prepare(<<<EOF
SELECT *
FROM `history`
WHERE `v` LIKE 'PLAY %'
ORDER BY `created` DESC
LIMIT 20
EOF
  );

$result->execute();

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
  $url = get_url(substr($row['v'], 5));
  $data = get_data($url);
  $title = htmlentities($data->title, NULL, 'UTF-8');
  $author_name = htmlentities($data->author_name, NULL, 'UTF-8');
  $author_url = htmlentities($data->author_url, NULL, 'UTF-8');
  $thumbnail = htmlentities($data->thumbnail_url, NULL, 'UTF-8');
  $note = htmlentities($row['note'], NULL, 'UTF-8');
  $active = strpos($play, substr($row['v'], 5)) ? ' active' : '';

  echo <<<EOF
      <div class="media$active">
        <div class="pull-left">
          <img src="$thumbnail" />
        </div>
        <h4>
          <a href="$url">$title</a>
        </h4>
        <p>Uploaded by <a href="$author_url">$author_name</a></p>
        <p>Added by $row[user] on $row[created]</p>
        <p>$note</p>
      </div>

EOF;
}
?>    </div>
<?
print_footer(
  'Copyright &copy; 2016 Will Yu',
  'A service of Blacker House'
);
?>  </body>
</html>
