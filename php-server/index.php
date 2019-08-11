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

    <!-- moment.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.24.0/moment.min.js" integrity="sha256-4iQZ6BVL4qNKlQ27TExEhBN1HFPvAvAMbFavKKosSWQ=" crossorigin="anonymous"></script>

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
    let API_KEY = 'AIzaSyCR-P0eRzSQ6HWfUyKsSzPcoESNElF1ZFU';
    async function get_video_data(ids) {
        if (ids == null) {
            return null;
        }

        let id_list = ids.join(',');

        let res = await fetch(`https://content.googleapis.com/youtube/v3/videos?id=${id_list}&part=snippet,contentDetails&key=${API_KEY}`);
        let json = await res.json()

        let videos = json.items.map(
        item => {
            return {
                author_name: item.snippet.channelTitle,
                author_url: `https://youtube.com/channel/${item.snippet.channelId}`,
                title: item.snippet.title,
                thumbnail: item.snippet.thumbnails.medium.url,
                url: `https://youtu.be/${item.id}`,
                duration: moment.duration(item.contentDetails.duration)
            }
        });
        return videos;
    }

    // TODO moment.js doesn't seem to have a function that does this
    function format_duration(duration) {
        var ret = "";
        if (duration.asHours() > 1) {
            var hoursToPrint = Math.floor( duration.asHours() );
            if (hoursToPrint < 10)
                ret += "0";
            ret += `${hoursToPrint}:`;
        } // omit hours if duration is < 1 hour
        if (duration.minutes() < 10)
            ret += "0";
        ret += `${duration.minutes()}:`;
        if (duration.seconds() < 10)
            ret += "0";
        ret += `${duration.seconds()}`;
        return ret;
    }
    function format_action(action) {
        var result = '<li class="list-group-item">';
        result += `<b>${action.action}</b>`;
        if (action.user) {
            result += ` by ${action.user} `
        }
        result += ` on ${action.time}`
        if (action.note.length > 0) {
            result += `<blockquote class="blockquote">${action.note}</blockquote>`
        }
        result += '</li>';
        return result;
    }
    function format_song_elem(song) {
        if (song == null)
            return "";

        return `<div class="row vid-listed">
            <img src="${song.thumbnail}" class="col-md-3 col-sm-5 col-xs-12" />
            <div class="col-md-9 col-sm-7 col-xs-12">
                <h4>
                    <a href="${song.url}">${song.title}</a>
                </h4>
                <p>${format_duration(song.duration)}, Uploaded by <a href="${song.author_url}">${song.author_name}</a></p>
                <ul class="list-group">
                ${song.actions.map( (act) => format_action(act) ).reduce((a, b) => a + b) }
                </ul>
            </div>
        </div>`
    }

    async function update() {
        console.log('updating...');
        let response = await fetch('process.php?status');
        let data = await response.json();

        let queue_div_inner = '';
        let history_div_inner = '';

        let songs = [];
        if (data.history && data.history.length > 0) {
            let song_data = await get_video_data(data.history.map(x => x != null ? x.vid : null));
            data.history.map((song, i) => song == null ? null : Object.assign({ actions: song.actions }, song_data[i]))
                .map((song) => { history_div_inner += format_song_elem(song); });
        }

        var queueDuration = moment.duration(0);
        if (data.queue && data.queue.length > 0) {
            let song_data = await get_video_data(data.queue.map(x => x.vid));
            data.queue
                .map((song, i) => song == null ? null :
                    Object.assign({ actions: song.actions }, song_data[i]))
                .map((song) => {
                    queue_div_inner += format_song_elem(song);
                    queueDuration = queueDuration.add(song.duration);
                })
            ;
        }
        $("#queueDuration").html( format_duration(queueDuration) );

        document.getElementById('queue_list').innerHTML = queue_div_inner;
        document.getElementById('history_list').innerHTML = history_div_inner;


        if (data.client_connected) {
            $('#client_status').html(`<h3 class='alert alert-success'>Client connected and ${data.status}</h3>`);
        } else {
            $('#client_status').html("<h3 class='alert alert-danger'>Client disconnected.</h3>");
        }
        // TODO: better status messages?

        if (data.current) {
            let currentData = await get_video_data([data.current.vid]);
            let displayCurrent =
                Object.assign({actions: data.current.actions}, currentData[0]);

            document.getElementById('playing_now').innerHTML = format_song_elem(displayCurrent);
        } else {
            document.getElementById('playing_now').innerHTML = `<div class="alert alert-info"><h3>No Song Playing.</h3></div>`;
        }
    }
    function get_req(action) {
        fetch(`process.php?action=${action}`).then(update());
    }

    let lock = false;
    function server_action(error, formData, successCallback) {
        if (error) {
            $('#js_error_div').text(error);
            $('#js_error_div').css('display', '');
            clearTimeout(); // prevent odd behavior of overlapping timeout windows
            setTimeout(() => { $('#js_error_div').css('display', 'none') }, 5000);
            lock = false;
        } else if (!lock) {
            lock = true;

            fetch('process.php', {
                method: 'POST',
                body: formData,
            }).then((res) => {
                lock = false;
                update();
                if (res.status === 200) {
                    successCallback();
                } else {
                    $('#error_code').text(res.status);
                    $('#failure_div').css('display', '');
                    setTimeout(() => { $('#failure_div').css('display', 'none') }, 5000);
                    res.json().then((resp) => {$("#error_message").text(resp.message);});
                }
            });
        }
    }

    function submit_song() {
        let url = $('#url').val();
        let note = $('#note').val();
        let user = $("#user").val();

        let formData = new FormData();
        formData.append('url', url);
        formData.append('note', note);
        formData.append('user', user);

        var error = null;
        if (user == "") {
            error = "Error! Name is required to submit song.";
        } else if (url == "") {
            error = "Error! YouTube URL is required.";
        }

        server_action(error, formData, () => {
            $('#url').val('');
            $('#note').val('');

            $('#success_div').css('display', '');
            $('#success_div').text('Success! Song added to queue.');
            setTimeout(() => { $('#success_div').css('display', 'none') }, 5000);
        });
    }

    function player_action(action) {
        let note = $('#note').val();
        let user = $("#user").val();

        let formData = new FormData();
        formData.append('action', action);
        formData.append('note', note);
        formData.append('user', user);

        var error = null;
        if (user == "") {
            error = "Error! Name is required for player actions.";
        }

        server_action(error, formData, () => {
            $('#note').val('');

            $('#success_div').text(`Performed '${action}' action successfully`);
            $('#success_div').css('display', '');
            setTimeout(() => { $('#success_div').css('display', 'none') }, 5000);
        });
    }

    $(function() {
        $("#user").val(localStorage.getItem("username"));

        $("#user").change(function () {
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
                <div id="bad_ip_div" class="alert alert-info col-xs-12">
                    <b>New features!</b>
                    You can now provide a note to accompany a play, pause, or skip action.
                    These notes are displayed anonymously (subject to change, see the Farther chat for opinions).
                </div>
            </div>

            <div class="row">
                <div id="success_div" class="alert alert-success col-xs-12" style="display: none">

                </div>
                <div id="failure_div" class="alert alert-danger col-xs-12" style="display: none">
                    Error! Song not added to queue. Error <a id="error_code"></a>: <span id="error_message"></span>
                </div>
                <div id="js_error_div" class="alert alert-danger col-xs-12" style="display: none">

                </div>
            </div>

            <span class="submit-form" aria-label="song submission">
                <div class="row">
                    <label class="col-sm-4 col-xs-12" for="user">Wiki username</label>
                    <input class="col-sm-8 col-xs-12" type="text" id="user" name="user" />
                </div>
                <div class="row"><div style="text-align: center;" class="col-xs-12">
                Actual password system still in progress, plz be nice
                </div></div>

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
                            <button class="btn btn-success col-xs-4" onclick="player_action('resume')"><span class="accessible-exclude glyphicon glyphicon-play"></span></button>
                            <button class="btn btn-success col-xs-4" onclick="player_action('skip')"><span class="accessible-exclude glyphicon glyphicon-fast-forward"></span></button>
                            <button class="btn btn-success col-xs-4" onclick="player_action('pause')"><span class="accessible-exclude glyphicon glyphicon-pause"></span></button>
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
                <h2>Queue (<span id="queueDuration">00:00</span>)</h2>
            </div>
            <div id="queue_list" class="row">

            </div>

            <div class="row section-header">
                <h2>History</h2>
            </div>
            <div id="history_list" class="row">

            </div>

            <!-- <div id="shortcutButtons" class="row">
                <button class="submitShortcut btn btn-primary col-md-4 col-md-offset-4 col-sm-8 col-sm-offset-2 col-xs-12" data-ytid="kJQP7kiw5Fk">
                    This is so sad. Farther, play <i>Despacito</i>.
                </button>
            </div> -->

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
                (partially derived from <a href="https://github.com/ejaszewski/Nearer">Nearer</a>
                under the MPL 2.0 license) <br>
                A service of Dabney Hovse
            </p>
        </div>
    </div>
    </div>
</body>
</html>
