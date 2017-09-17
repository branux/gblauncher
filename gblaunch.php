<?PHP

/*
INSTRUCTIONS:

Download Gunbot zip file to this working dirctory.

Save Complete Gunbot config.js to this working dorectory.


WHAT THIS SCRIPT DOES:
(eg for GB 5.0.4 bittrex BTC-ARK and BTC-BCC)

Read config.js and extract all pairs, exchanges and strategies.
For each pair/exchange/strat combo create GUNBOT_5.0.4/exchange_PAIR_strat/ folder and unzip GB.
Write minimal config.js for the specific pair.

Launch pair with output logged


CONFIG:
*/
$debug=false;

$gbver = "5_0_4"; //version only - do not rename source .zip

//todo:
$gb_md5sum = ""; //


$base_ws_port = 5001; //starting port to use for websockets

$start_delay = 2;  //delay between starting bots in seconds








//dont change after here
$writeconfig = $writepm2 = $unzipgb = $createdirs = $createpm2 = $startbots = $stopbots = $delete = false;
$arg = (isset($argv[1]) && (NULL!==$argv[1])?$argv[1]:'help');

switch($arg){
	case "build":
		$createdirs = true;
		$unzipgb = true;
		$writeconfig = true;
		$writepm2 = true;
		$startbots = true;
	break;
	case "start":
		$startbots = true;
	break;
	case "stop":
		$stopbots = true;
	break;
	case "restart":
		$startbots = true;
	break;
	case "reload":
		$writeconfig = true;
	break;
	case "update":
		$unzipgb2 = true;
		$writeconfig = true;
		$startbots = true;
	break;
	case "clean":
		$delete = true;
		$stopbots = true;

	break;
	case "exportlog":


	break;
	default:
		echo "Usage instructions:".PHP_EOL.PHP_EOL."build [id]          build bots".PHP_EOL;
		echo "start [id]          Start bot, optional id or all according to start_delay".PHP_EOL;
		echo "stop [id]           Stop bot, optional id or all, according to stop_delay".PHP_EOL;
		echo "restart [id]        Restart bot(s)".PHP_EOL;
		echo "reload [id]         Rebuild config in-place [for bot(s)]".PHP_EOL;
		echo "update              Extract new Gunbot, rebuild configs and restart all bots".PHP_EOL;
		echo "exportlog id file   Export logfile from specified bot to file".PHP_EOL;
		die();
	break;
}



$basedir = dirname(__FILE__);

$config = json_clean_decode(file_get_contents($basedir.'/config.js'),true);
$globalsettings = array();
$globalsettings['exchanges'] = $config['exchanges'];
$globalsettings['bot'] = $config['bot'];
$globalsettings['imap_listener'] = $config['imap_listener'];
$globalsettings['optionals'] = $config['optionals'];

$overrides = $config['overrides'];
$strategies = $config['strategies'];
$pairs = $config['pairs'];

//sort and filter pairs



//var_dump($pairs);

//start looping over the pairs
foreach($pairs as $exchange=>$pa){
$e = strtolower(substr($exchange,0,1));
	foreach($pa as $pair=>$opts){
		$n = $e.'_'.$pair.'_'.((array_key_exists($opts['strategy'],$strategies) && array_key_exists('REQUIRES',$strategies[$opts['strategy']]))?$strategies[$opts['strategy']]['REQUIRES']:$opts['strategy']);
echo "Processing ".$n.PHP_EOL;
		$p = $basedir.'/gunbot_launcher/'.$n.'/';
		//make folder structure
		if($createdirs){
			if($debug)		echo "mkdir: " .$p.'/tulind/lib/binding/Release/node-v57-linux-x64/'.PHP_EOL;
			@		mkdir($p.'tulind/lib/binding/Release/node-v57-linux-x64/',0777,true);
		}
		//extract gunbot files
		if($unzipgb){
			if($debug)		echo "exec: " .'unzip -j '.$basedir.'/GUNBOT_V'.$gbver.'.zip GUNBOT_V'.$gbver.'/tulind/lib/binding/Release/node-v57-linux-x64/tulind.node -d '.$p.'tulind/lib/binding/Release/node-v57-linux-x64'.PHP_EOL;
			exec('unzip -o -qq -j '.$basedir.'/GUNBOT_V'.$gbver.'.zip GUNBOT_V'.$gbver.'/tulind/lib/binding/Release/node-v57-linux-x64/tulind.node -d '.$p.'tulind/lib/binding/Release/node-v57-linux-x64');
			if($debug)		echo "exec: " .'unzip -j '.$basedir.'/GUNBOT_V'.$gbver.'.zip GUNBOT_V'.$gbver.'/gunthy-linx64 -d '.$p.PHP_EOL;
			exec('unzip -o -qq -j '.$basedir.'/GUNBOT_V'.$gbver.'.zip GUNBOT_V'.$gbver.'/gunthy-linx64 -d '.$p);
			//sleep(2);
			if($debug)		echo "chmod +x :" .$p.'gunthy-linx64'.PHP_EOL;
			exec('chmod +x '.$p.'gunthy-linx64');
		}

		//create config
		if($writeconfig){
			$config = $globalsettings;
			if(array_key_exists('override',$opts) && array_key_exists('CUSTOM',$opts['override'])){
				//custom overrides method
				if(!array_key_exists($opts['override']['CUSTOM'],$overrides)){
					echo "ERROR: $n trying to use non-existant custom overrides. BAILING OUT.".PHP_EOL;
					continue;
				}
				$c = $overrides[$opts['override']['CUSTOM']]['REQUIRES'];
				if($c !== $opts['strategy']){
					echo "ERROR: $n trying to use " . $c . " custom overrides on ". $opts['strategy']." BAILING OUT.".PHP_EOL;
					continue;
				}
				$config['pairs']=array($exchange=>array($pair=>$opts));
				foreach($overrides[$opts['override']['CUSTOM']] as $key=>$val){
					if($key == "REQUIRES")continue;
					$config['pairs'][$exchange][$pair]['override'][$key] = $val;
				}
				unset($config['pairs'][$exchange][$pair]['override']['CUSTOM']);
				$config['strategies'][$opts['strategy']] = $strategies[$opts['strategy']];
			}
			elseif(array_key_exists($opts['strategy'],$strategies) && array_key_exists('REQUIRES',$strategies[$opts['strategy']])){
				//custom strategy method
				$config['pairs']=array($exchange=>array($pair=>$opts));
				$s = array();
				foreach($strategies[$opts['strategy']] as $key=>$val){
					if($key == 'REQUIRES'){
						$t = $val;
						$config['pairs'][$exchange][$pair]['strategy']=$t;
					}else{
						$s[$key]=$val;
					}
				}
				$config['strategies'][$t] = $s;
			}
			else{
				$config['pairs']=array($exchange=>array($pair=>$opts));
				$config['strategies'][$opts['strategy']] = $strategies[$opts['strategy']];
			}
			$config['ws']['port'] = $base_ws_port++;


			//write config
			file_put_contents($p.'config.js',json_encode($config, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT));
			if(!$startbots)sleep($start_delay);
		}

		if($writepm2){
			//write pm2 process file
$f = 'module.exports = {
  apps : [{
    name   : "'.$n.'",
    cwd: "'.$p .'",
    script: "gunthy-linx64",
    watch: true,
    args: "--color"
  }]
}';

			file_put_contents($p.$n.'.config.js',$f);
		}

		if($startbots){
			//launch bot
			if($debug) echo "exec: ". 'pm2 restart '.$p.$n.'.config.js';
			exec('pm2 start '.$p.$n.'.config.js');
			sleep($start_delay);
		}

		if($stopbots){
			//launch bot
			if($debug) echo "exec: ". 'pm2 stop '.$n;
			exec('pm2 stop '.$n);
			if(!$delete)sleep($start_delay);
		}

		if($delete){
			exec('pm2 delete '.$n);
			exec('rm -rf '.$p);
		}
	}
}


if($delete){
	exec('rm -rf '.$basedir.'/gunbot_launcher/');
}



















// Helper Functions

function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0) {
    // search and remove comments like /* */ and //
    $json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);
    if(version_compare(phpversion(), '5.4.0', '>=')) {
        $json = json_decode($json, $assoc, $depth, $options);
    }
    elseif(version_compare(phpversion(), '5.3.0', '>=')) {
        $json = json_decode($json, $assoc, $depth);
    }
    else {
        $json = json_decode($json, $assoc);
    }
    return $json;
}



?>
