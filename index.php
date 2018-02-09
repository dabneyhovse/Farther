<?php
include(__DIR__ . '/../lib/include.php');
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <?php print_head('Nearer'); ?>
  </head>
  <body>
    <div id="main">
      <h1>Nearer</h1>
<?php

$subtitles = array(
  'Beats Ricketts Music',
  "Don't Play the Ride",
  'Play Loud, Play Proud',
  'The Day the Music Died',
  'Better Than Ever Before!',
  'New and Improved!'
);

$subtitle = $subtitles[mt_rand(0, count($subtitles) - 1)];

echo <<<EOF
      <h2>$subtitle</h2>
EOF;
?>
      <style>
        .control {
          display: inline-block;
          margin: 0.4em;
          border: 1px solid #111;
          border-radius: 0.3em;
          outline: 0;
          padding: 0.2em 0.6em;
          width: auto;
          background-color: #222;
          color: #ccc;
          text-decoration: none;
          font-size: 0.9em;
          line-height: 2;
          cursor: default;
        }
        .control:hover {
          border-color; #222;
          background-color: #333;
          color: #ccc;
        }
        .control:active {
          border-color: #222;
          background-color: #111;
          color: #999;
        }
        .control.disabled {
          border-color: #444;
          background-color: #444;
          color: #999;
	}
	.mediasp {
          margin: 0;
          padding: 0;
        }
        .mediasp > .pull-left {
	  position: relative;
	  width: 30%;
	  top: -2em;
	  right: 0%;
	  bottom: 0;
	  /*! left: 0; */
	  margin-left: 0;
	}
	.mediasp:after {
          content: "";
          display: block;
          clear: both;
        }	
      </style>

      <script>
        function update() {
        console.log('updating...');
        fetch('https://blacker.caltech.edu/nearer_ethan_sandbox/process.php?status', {
          credentials: 'include',
        }).then(res => res.json()).then(data => {
          let song_div_inner = '';
          data.songs.forEach((song) => {
            let song_element = `<div class="media">
              <div class="pull-left">
                <img src="${song.thumbnail}" />
              </div>
              <h4>
                <a href="${song.url}">${song.title}</a>
              </h4>
              <p>Uploaded by <a href="${song.author_url}">${song.author_name}</a></p>
              <p>Added by ${song.added_by} on ${song.added_on}</p>
              <p>${song.note}</p>
            </div>`;
            song_div_inner += song_element;
          });
	  document.getElementById('recently_added').innerHTML = song_div_inner;

	  if (data.current) {
	      document.getElementById('playing_now').innerHTML = `\
              <div class="pull-left">
                <img src="${data.current.thumbnail}" />
              </div>
              <h4>
                <a href="${data.current.url}">${data.current.title}</a>
              </h4>
	      <p>Uploaded by <a href="${data.current.author_url}">${data.current.author_name}</a></p>`;
	  } else {
              document.getElementById('playing_now').innerHTML = `<h3>No Song Playing.</h3>`;
          }
        });
      }

      function get_req(action) {      
	fetch(`https://blacker.caltech.edu/nearer_ethan_sandbox/process.php?action=${action}`, {
          credentials: 'include',
        }).then(update());
      }

      let lock = false;

      function submit_song() {
        if (!lock) {
          lock = true;

          let url = $('#url').val();
          let note = $('#note').val();

          let formData = new FormData();
          formData.append('url', url);
          formData.append('note', note);
 
          fetch('https://blacker.caltech.edu/nearer_ethan_sandbox/process.php', {
            credentials: 'include',
            method: 'POST',
            body: formData,
          }).then((res) => {
            lock = false;
	    update();
	    if (res.status === 200) {
              res = res.json();

              $('#url').val('');
              $('#note').val('');

              $('#success_div').css('display', '');
              setTimeout(() => { $('#success_div').css('display', 'none') }, 5000);
            } else {
              $('#error_code').text(res.status);
              $('#failure_div').css('display', '');
              setTimeout(() => { $('#failure_div').css('display', 'none') }, 5000);
            }
          });
        }
      }
      </script>

      <div id="success_div" class="success" style="display: none">
          Success! Song added to queue.
      </div>
      <div id="failure_div" class="error" style="display: none">
          Error! Song not added to queue. Error code: <a id="error_code"></a>
      </div>
      <form onsubmit="submit_song(); return false;">
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
            <button type="submit" class="control">Submit</button>
            <div class="pull-right">
              <button class="control" onclick="get_req('resume')">&nbsp;&#9654;&nbsp;</button>
              <button class="control" onclick="get_req('skip')">&nbsp;&#9197;&nbsp;</button>
              <button class="control" onclick="get_req('pause')">&nbsp;&#9724;&nbsp;</button>
            </div>
          </div>
        </form>
      </div>
      
      <h2>Playing Now</h2>
      <div id="playing_now" class="media mediasp">
      
      </div>

      <h2>Recently Added</h2>
      <div id="recently_added">

      </div>

      <script>
      update();
      let updateInterval = setInterval(update, 30000);
      </script>

<?php
print_footer(
  'Copyright &copy; 2018 Ethan Jaszewski',
  'A service of Blacker House'
);
?>  </body>
</html>
