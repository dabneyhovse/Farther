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
    <link rel="stylesheet" href="/static/service_style.css">
    <script src="/static/accessibility.js"></script>

    <!-- app-specific -->
    <link rel="stylesheet" href="farther.css">
</head>
<body>
    <div id="site-container" class="container">
    <div id="page-header" class="row">
        <a href="/" class="col-md-2 col-sm-3 col-xs-4">
            <img src="/static/full-crest.png">
        </a>
        <div class="col-md-10 col-sm-9 col-xs-8">
            <h1>Farther</h1>
            Like <a href="https://blacker.caltech.edu/nearer">Nearer</a>, but Worse&trade;
        </div>
    </div>
    <script>
    function update() {
        console.log('updating...');
        fetch('process.php?status').then(res => res.json()).then(data => {
            let song_div_inner = '';
            data.songs.forEach((song) => {
                let song_element = `<div class="row vid-listed">
                    <img src="${song.thumbnail}" class="col-md-3 col-sm-5 col-xs-12" />
                    <div class="col-md-9 col-sm-7 col-xs-12">
                        <h4>
                            <a href="${song.url}">${song.title}</a>
                        </h4>
                        <p>Uploaded by <a href="${song.author_url}">${song.author_name}</a></p>
                        <p>Added by ${song.added_by} on ${song.added_on}</p>
                        <p>${song.note}</p>
                    </div>
                </div>`;
                song_div_inner += song_element;
            });
            document.getElementById('recently_added').innerHTML = song_div_inner;

            if (data.client_connected) {
                $('#client_status').html(`<h3 class='alert alert-success'>Client connected and ${data.status}</h3>`);
            } else {
                $('#client_status').html("<h3 class='alert alert-danger'>Client disconnected.</h3>");
            }

            if (data.current) {
    	        document.getElementById('playing_now').innerHTML = `\
                    <img src="${data.current.thumbnail}" class="col-md-3 col-sm-5 col-xs-12" />
                    <div class="col-md-9 col-sm-7 col-xs-12">
                        <h4>
                            <a href="${data.current.url}">${data.current.title}</a>
                        </h4>
        	            <p>Uploaded by <a href="${data.current.author_url}">${data.current.author_name}</a></p>
                    </div>`;
    	    } else {
                document.getElementById('playing_now').innerHTML = `<div class="alert alert-info"><h3>No Song Playing.</h3></div>`;
            }
        });
    }
    function get_req(action) {
        fetch(`https://dabney.caltech.edu/farther/process.php?action=${action}`).then(update());
    }

    let lock = false;
    function submit_song() {
        if (!lock) {
            lock = true;

            let url = $('#url').val();
            let note = $('#note').val();
            let user = $("#user").val();

            let formData = new FormData();
            formData.append('url', url);
            formData.append('note', note);
            formData.append('user', user);
            // TODO: store in localStorage or something, we don't want a
            // formal login system

            var error = null;
            if (user == "") {
                error = "Error! Name is required.";
            } else if (url == "") {
                error = "Error! YouTube URL is required.";
            }

            if (error) {
                $('#js_error_div').text(error);
                $('#js_error_div').css('display', '');
                clearTimeout(); // prevent odd behavior of overlapping timeout windows
                setTimeout(() => { $('#js_error_div').css('display', 'none') }, 5000);
                lock = false;
            } else {
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
    }

    $(function() {
        $("#user").val(localStorage.getItem("username"));

        $("#user").change(function () {
            console.log("change");
            localStorage.setItem("username", $("#user").val());
        });
    });

    </script>
    <div class="row">
        <div id="page-content" class="col-xs-12">
            <?php
            require_once("check_ip.php");
            if (! valid_ip($_SERVER['REMOTE_ADDR'])) :
            ?>
            <div class="row">
                <div id="bad_ip_div" class="alert alert-warning col-xs-12">
                    <span class="glyphicon glyphicon-map-marker"></span>
                    <b>Warning!</b> You must connect be on the Caltech network to use Farther.
                </div>
            </div>
            <?php endif; ?>

            <?php
            $current = new DateTime();
            $current->setTimezone(new DateTimeZone('America/Los_Angeles'));

            // quiet hours are 12am-7am on weekdays, 2am-7am on weekends
            $quiet_start = clone $current;
            $quiet_end = clone $current;
            if ($current->format('w') == '0' || $current->format('w') == '6') {
                $quiet_start->setTime(2, 0);
            } else {
                $quiet_start->setTime(0, 0);
            }
            $quiet_end->setTime(7, 0);

            if ($current > $quiet_start && $current < $quiet_end) :
            ?>
            <div class="row">
                <div id="bad_ip_div" class="alert alert-warning col-xs-12">
                    <span class="glyphicon glyphicon-time"></span>
                    <b>Warning!</b> It's currently within Quiet Hours, so music should not be played loudly.
                </div>
            </div>
            <?php endif; ?>



            <div class="row">
                <div id="success_div" class="alert alert-success col-xs-12" style="display: none">
                    Success! Song added to queue.
                </div>
                <div id="failure_div" class="alert alert-danger col-xs-12" style="display: none">
                    Error! Song not added to queue. Error code: <a id="error_code"></a>
                </div>
                <div id="js_error_div" class="alert alert-danger col-xs-12" style="display: none">

                </div>
            </div>

            <span class="submit-form" aria-label="song submission">
                <div class="row">
                    <label class="col-sm-4 col-xs-12" for="user">Whomst are you?</label>
                    <input class="col-sm-8 col-xs-12" type="text" id="user" name="user" />
                </div>
                <div class="row">
                    <label class="col-sm-4 col-xs-12" for="url">YouTube URL</label>
                    <input class="col-sm-8 col-xs-12" type="text" id="url" name="url" />
                </div>
                <div class="row">
                    <label class="col-sm-4 col-xs-12" for="note">Note (optional)</label>
                    <input class="col-sm-8 col-xs-12" type="text" id="note" name="note" maxlength="255" />
                </div>
                <div class="row">
                    <button class="btn btn-primary col-sm-offset-4 col-sm-2 col-xs-12" onclick="submit_song();" class="control">Submit</button>

                    <div class="col-sm-offset-2 col-sm-4 col-xs-12">
                        <div class="row controls" aria-label="player controls">
                            <button class="btn btn-success col-xs-4" onclick="get_req('resume')"><span class="glyphicon glyphicon-play"></span></button>
                            <button class="btn btn-success col-xs-4" onclick="get_req('skip')"><span class="glyphicon glyphicon-fast-forward"></span></button>
                            <button class="btn btn-success col-xs-4" onclick="get_req('pause')"><span class="glyphicon glyphicon-pause"></span></button>
                        </div>
                    </div>
                </div>
            </span>

            <div id="client_status" class="row"></div>

            <div class="row section-header">
                <h2>Playing Now</h2>
            </div>
            <div id="playing_now" class="row">

            </div>

            <div class="row section-header">
                <h2>Recently Added</h2>
            </div>
            <div id="recently_added" class="row">

            </div>

            <div id="shortcutButtons" class="row">
                <button class="submitShortcut btn btn-primary col-md-4 col-md-offset-4 col-sm-8 col-sm-offset-2 col-xs-12" data-ytid="SxTNhD5jTyQ">
                    This is so sad. Farther, play <i>Despacito</i>.
                </button>
            </div>

            <script>
            $(function () {
                $(".submitShortcut").click(function() {
                    $("#url").val( "https://youtube.com/watch?v=" + $(this).data("ytid") );
                    submit_song();
                });
            });

            update();
            let updateInterval = setInterval(update, 30000);
            </script>
        </div>
    </div>
    <div class="row">
        <div id="page-footer" class="col-xs-12">

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
    </div>
</body>
</html>
