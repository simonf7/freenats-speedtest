<?php // speedtest.inc.php -- SpeedTest test

/* Description

The test add-on uses the speedtest-cli python project in order run scheduled
download/upload tests.

Therefore speedtest-cli must be installed, it will work with the version in the
default Ubuntu repository. Apache/PHP must also have permission to utilise the
command line through the shell_exec() function.

*/

global $NATS;

class SpeedTest_Functions
{
    var $tempFile       = '/tmp/speedtest-cli.tmp';
    var $defaultCmd     = 'speedtest-cli';
    var $testOptions    = ' --simple';


    // get the SpeedTest results from a temporary cache file
    public function get($expiration = 600) // default 10 minutes  
    {           
        $cache_path = $this->tempFile;  
        
        if (!@file_exists($cache_path))  
            return FALSE;  
        
        if (filemtime($cache_path) < (time() - $expiration))  
            return FALSE;  
        
        if (!$fp = @fopen($cache_path, 'rb'))  
            return FALSE;  
        
        flock($fp, LOCK_SH);  
        $cache = '';  
        if (filesize($cache_path) > 0)  
            $cache = unserialize(fread($fp, filesize($cache_path)));  
        else  
            $cache = NULL;  

        flock($fp, LOCK_UN);  
        fclose($fp);  
        
        return $cache;  
    }  


    // save the SpeedTest results to a file
    public function set($data)  
    {  
        $cache_path = $this->tempFile;

        if ( ! $fp = fopen($cache_path, 'wb'))  
            return FALSE;  

        if (flock($fp, LOCK_EX))  
        {  
            fwrite($fp, serialize($data));  
            flock($fp, LOCK_UN);  
        }  
        else  
            return FALSE;  

        fclose($fp);  
        @chmod($cache_path, 0777); 

        return TRUE;  
    }  


    // Either pull the speedtest results from the temporary file, or actually run it
    // - even though you can change the frequency of test runs in the FreeNATS GUI we
    //   don't actually want the speedtest running anymore than once every ten minutes
    function runSpeedTest($from, $to, $cmd = '')
    {
        // as the SpeedTest takes a while use a temporary file to cache results
        $result = $this->get();

        // if no cached result, rerun the SpeedTest
        if (!$result) {
            // if $cmd isn't set, just default to running speedtest-cli
            if (!$cmd) {
                $cmd = $this->defaultCmd;
            }

            // run the command
            $result = shell_exec($cmd . $this->testOptions);
            
            // save the result
            $this->set($result);
        }

        $speed = 0; // default gives a failure

        if ($result) {
            // make sure the command was actually found
            if (strpos($result, 'command not found')===false) {
                // look for text between the $from .. $to
                // - could probably use some fancy pants regular expression, but this'll do
                if (strpos($result, $from)!==false) {
                    list($before, $after) = explode($from, $result);

                    if (strpos($after, $to)!==false) {
                        list($speed, $junk) = explode($to, $after);
                    }
                }
            }
        }
 
        return $speed;
    }
}


// the tests, ping, up and down
global $NATS;

if (isset($NATS)) {
    // Download test
    class SpeedTest_Download_Test extends FreeNATS_Local_Test
    {
        function DoTest($testname,$param,$hostname="",$timeout=-1,$params=false)
        {
            $speedTest = new SpeedTest_Functions();
            
            // if tests were run in the last 5 minutes, get them or run the speedtest again
            $result = $speedTest->runSpeedTest('Download: ', ' Mbit/s');
           
            return $result;
        }
        
        function Evaluate($result)
        {
            if ($result>0)
                return 0;
            return 2;
        }
    }

    $params = array();

    $NATS->Tests->Register(
        'speedtest_dl',
        'SpeedTest_Download_Test',
        $params,
        'SpeedTest Download',
        1,
        'SpeedTest Download'
    );
    $NATS->Tests->SetUnits("speedtest_dl","Megabits/second","Mbit/s");


    // Upload test
    class SpeedTest_Upload_Test extends FreeNATS_Local_Test
    {
        function DoTest($testname,$param,$hostname="",$timeout=-1,$params=false)
        {
            $speedTest = new SpeedTest_Functions();
            
            // if tests were run in the last 5 minutes, get them or run the speedtest again
            $result = $speedTest->runSpeedTest('Upload: ', ' Mbit/s');
           
            return $result;
        }
        
        function Evaluate($result)
        {
            if ($result>0)
                return 0;
            return 2;
        }
    }

    $params = array();

    $NATS->Tests->Register(
        'speedtest_ul',
        'SpeedTest_Upload_Test',
        $params,
        'SpeedTest Upload',
        1,
        'SpeedTest Upload'
    );
    $NATS->Tests->SetUnits("speedtest_ul","Megabits/second","Mbit/s");


    // Ping test
    class SpeedTest_Ping_Test extends FreeNATS_Local_Test
    {
        function DoTest($testname,$param,$hostname="",$timeout=-1,$params=false)
        {
            $speedTest = new SpeedTest_Functions();
            
            // if tests were run in the last 5 minutes, get them or run the speedtest again
            $result = $speedTest->runSpeedTest('Ping: ', ' ms');
           
            return $result;
        }
        
        function Evaluate($result)
        {
            if ($result>0)
                return 0;
            return 2;
        }
    }

    $params = array();

    $NATS->Tests->Register(
        'speedtest_ping',
        'SpeedTest_Ping_Test',
        $params,
        'SpeedTest Ping',
        1,
        'SpeedTest Ping'
    );
    $NATS->Tests->SetUnits("speedtest_ping","milliseconds","ms");
}
