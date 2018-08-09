<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title>Farther [Dabney Hovse]</title>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>

    <!-- Bootstrap -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" integrity="sha384-rHyoN1iRsVXV4nD0JutlnGaslCJuC7uwjduW9SVrLvRYooPp2bWYgmgJQIXwl/Sp" crossorigin="anonymous">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js" integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa" crossorigin="anonymous"></script>

    <!-- Dabney things -->
    <link rel="stylesheet" href="css/main.css">
    <script src="js/accessibility.js"></script>

    <!-- app-specific -->
    <link rel="stylesheet" href="css/farther.css">
</head>
<body>
    <div id="site-container" class="container">
    <div id="page-header">
        <a href="/" class="col-md-2 col-sm-3 col-xs-4">
            <img src="static/full-crest.png">
        </a>
        <div class="col-md-10 col-sm-9 col-xs-8">
            <h1>Farther</h1>
            Like <a href="https://blacker.caltech.edu/nearer">Nearer</a>, but Worse&trade;
        </div>
    </div>
    <script>
    function update() {
        console.log('updating...');
        fetch('/process.php?status', {
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
        fetch(`/process.php?action=${action}`, {
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
            formData.append('user', username);

            fetch('process.php', {
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
    <div id="page-content">
        <div class="row">
            <div id="success_div" class="success col-xs-12" style="display: none">
                Success! Song added to queue.
            </div>
            <div id="failure_div" class="error col-xs-12" style="display: none">
                Error! Song not added to queue. Error code: <a id="error_code"></a>
            </div>
        </div>

        <form onsubmit="submit_song(); return false;">
            <div class="row">
                <label class="col-sm-4 col-xs-12" for="url">YouTube URL</label>
                <input class="col-sm-8 col-xs-12" type="text" id="url" name="url" />
            </div>
            <div class="row">
                <label class="col-sm-4 col-xs-12" for="note">Note (optional)</label>
                <input class="col-sm-8 col-xs-12" type="text" id="note" name="note" maxlength="255" />
            </div>
            <div class="row">
                <button class="btn btn-primary col-sm-offset-4 col-sm-2 col-xs-12" type="submit" class="control">Submit</button>

                <div class="controls btn-group col-sm-offset-2 col-sm-4 col-xs-12" role="group" aria-label="player controls">
                    <button class="btn btn-success btn-weight" onclick="get_req('resume')">&#9654;</button>
                    <button class="btn btn-success btn-weight" onclick="get_req('skip')">&#9197;</button>
                    <button class="btn btn-success btn-weight" onclick="get_req('pause')">&#9724;</button>
                </div>
            </div>
        </form>

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
    </div>
    <div id="page-footer">

        <hr />
        <div class="accessibility">
            <label for="accessibilityMode">Accessibility Mode</label>
            <input id="accessibilityMode" type="checkbox" accesskey="a" />
        </div>

        <p>
            Copyright &copy; 2018 Nicholas Currault <br>
            (partially derived from <a href="https://github.com/ejaszewski/nearer-client">Nearer</a>
            under the MPL 2.0 license) <br>
            A service of Dabney Hovse
        </p>
    </div>
    </div>
</body>
</html>
