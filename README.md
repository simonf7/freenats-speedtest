# freenats-speedtest
Simple test utilising speedtest-cli to add SpeedTest results into FreeNATS (https://github.com/purplepixie/freenats).

## About
The test add-on uses the speedtest-cli python project in order run scheduled download/upload tests.

Therefore speedtest-cli must be installed, it will work with the old-ish version in the default Ubuntu repository. Apache/PHP must also have permission to utilise the command line through the shell_exec() function - sorry.
