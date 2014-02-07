<?php
$debug=false;
$time_pagestarted=microtime(true);

function trace($info,$level="DEBUG"){
	global $debug;
	global $time_pagestarted;
	if(!$debug AND $level=="DEBUG"){
		// only print info if in debug mode
		return false;
	}
	$secs=round(microtime(true) - $time_pagestarted,3);
	if(is_array($info)){
		echo "\n<-- $level @ ${secs}s:\n";
		print_r($info);
		echo "\n-->\n";
	} else {
		printf("\n<-- %s @ %06.3fs: %s -->\n",$level,$secs,htmlspecialchars($info));
	}
}

Class Cache {
	var $enabled=false;

	function __construct($cachedir="cache"){
		if(!file_exists("$cachedir/.")){
			if(mkdir($cachedir,0666,true)){
				trace("created cache dir [$cachedir]");
				$this->enabled=true;
				$this->cachedir=$cachedir;
				return true;
			} else {
				trace("cannot create dir [$cachedir]","ERROR");
				$this->enabled=false;
				return false;
			}
				;
		} else {
			$this->enabled=true;
			$this->cachedir=$cachedir;
			return true;
		}
	}

	function get($id,$group="cache",$maxsecs=295){
		if(!$this->enabled){
			return false;
		}
		$cachefile=$this->mkfilename($id,$group);
		if(!file_exists($cachefile)){
			trace("Cache::get : no cache [$cachefile] yet");
			return false;
		}
		// so the cache file exists
		if((time()-filemtime($cachefile)) > $maxsecs){
			trace("Cache::get : cache [$cachefile] too old");
			return false;
		}
		trace("Cache::get : cache [$cachefile] OK");
		return file_get_contents($cachefile);
	}

	function get_arr($id,$group="cache",$maxsecs=295){
		if(!$this->enabled){
			return false;
		}
		$cachefile=$this->mkfilename($id,$group);
		if(!file_exists($cachefile)){
			trace("Cache::get_arr : cache [$cachefile] not yet");
			return false;
		}
		// so the cache file exists
		if((time()-filemtime($cachefile)) > $maxsecs){
			trace("Cache::get_arr : cache [$cachefile] too old");
			return false;
		}
		trace("Cache::get_arr : cache [$cachefile] is used");
		return unserialize(file_get_contents($cachefile));
	}

	function set($id,$group="cache",$value){
		if(!$this->enabled){
			return false;
		}
		if(rand(0,100) > 95){
			$this->cleanup();
		}
		$cachefile=$this->mkfilename($id,$group);
		trace("Cache::set : saving [$cachefile]");
		file_put_contents($cachefile,$value);
		return true;
	}

	function set_arr($id,$group="cache",$array){
		if(!$this->enabled){
			return false;
		}
		if(rand(0,100) > 95){
			$this->cleanup();
		}
		$cachefile=$this->mkfilename($id,$group);
		trace("Cache::set_arr : saving [$cachefile]");
		file_put_contents($cachefile,serialize($array));
		return true;
	}

	function cleanup($hours=24){
		if(!$this->enabled){
			return false;
		}
		$cachefiles=glob("$this->cachedir/*.temp");
		if($cachefiles){
			$treshold=time()-$hours*3600;
			$nbdeleted=0;
			foreach($cachefiles as $cachefile){
				if(filemtime($cachefile) < $treshold){
					unlink($cachefile);
					$nbdeleted++;
				}
			}
			if($nbdeleted > 0){
				trace("Cache::cleanup : removed $nbdeleted old cache files");
			} else {
				// no cache files deleted
			}
		} else {
			// no cache files yet, so nothing to do
		}
	}

	private function mkfilename($id,$group="cache"){
		$group=strtolower(substr($group,0,10));
		$begin=preg_replace("#[^a-zA-Z0-9]*#","",$id);
		$begin=substr($begin,0,10);
		$cacheid="$group.$begin." . substr(sha1($id),0,16);
		$cachefile=$this->cachedir . "/$cacheid.temp";
		return $cachefile;
	}


}

function cmdline($text,$folder=false,$cachesecs=30){
	if($folder){
		$path=realpath($folder);
		if(!$path){
			trace("cannot find folder [$folder]","ERROR");
			return false;
		}
		$line="cd \"$path\"; $text";
	} else {
		$line=$text;
	}
	if($cachesecs>0){
		$cc=New Cache;
		$result=$cc->get_arr($line,"bash",$cachesecs);
		if($result)	return $result;
	}
	$stdout=Array();
	$result=exec("$line",$stdout);
	trace("cmdline: [$line] = " . count($stdout) . " lines returned");
	if($cachesecs>0 AND $stdout){
		$cc=New Cache;
		$cc->set_arr($line,"bash",$stdout);
	}
	return $stdout;
}

function getparam($name,$default=false){
	if(isset($_GET[$name])){
		$value=$_GET[$name];
		trace("getparam: [$name] = [" . htmlspecialchars(substr($value,0,20)) . "]");
		return $value;
	}
	$value=$default;
	trace("getparam: [$name] = [" . htmlspecialchars(substr($value,0,20)) . "] (default)");
	return $value;
}

?>
