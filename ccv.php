<?php

require 'define.php';
require 'getplayerstatus.php';

$line = 0;
$comment_cnt = 0;
$last_no = 0;

$last_res = 0;

class reader
{
	public $fp;
	public $buf = "";

	public function read()
	{
		for(;;)
		{
			$z = strpos($this->buf, "\0");
			if($z !== false)
			{
				$str = substr($this->buf, 0, $z+1);
				$this->buf = substr($this->buf, $z+1);
				return $str;
			}
			$this->buf .= fread($this->fp, 4096);
		}
	}
}

function put_comment($str)
{
	global $line;
	global $comment_cnt;
	global $last_no;
	global $last_res;

	$xml = new SimpleXMLElement($str);

	$tagName = $xml->getName();
	if($tagName === "thread")
	{
		$last_res = (int)$xml['last_res'];
		echo "last_res=$last_res\n";
		return false;
	}
	if($tagName !== "chat")
	{
		echo "unknown tag name $tagName\n";
		return false;
	}

	$no        = (int)$xml['no'];
	$user_id   = (string)$xml['user_id'];
	$premium   = (int)$xml['premium'];
	$anonymity = (int)$xml['anonymity'];
	$text      = (string)$xml;

	if($last_no === 0)$last_no = $no-1;
	while(++$last_no < $no)
	{
		echo sprintf("%4d:%4d:%4d:ng commnet\n", $line++, $last_no, $comment_cnt++);
	}

	if(($premium&2) !== 0)
	{
		$comment_no = "    ";
	}
	else
	{
		$comment_no = sprintf("%4d", $comment_cnt++);
	}
	echo sprintf("%4d:%4d:%s:%-27s:%d%d:%s\n", $line++, $no, $comment_no, $user_id, $premium, $anonymity, $text);

	if($no >= $last_res)return true;

	return false;
}

	if(count($argv) < 3)exit;
	$id = $argv[1];

	$playerstatus = getplayerstatus($id, $argv[2]);
	if($playerstatus === false)
	{
		echo "getplayerstatus error $id\n";
		exit(1);
	}

	$xml = new SimpleXMLElement($playerstatus);
	$status = $xml['status'];
	if($status != 'ok')
	{
		echo "status $status\n";
		echo "error : ".$xml->error->code."\n";
		exit(1);
	}

	$title         = (string)$xml->stream->title;
	$description   = (string)$xml->stream->description;
	$provider_type = (string)$xml->stream->provider_type;
	$co            = (string)$xml->stream->default_community;
	$watch_count   = (int)$xml->stream->watch_count;
	$comment_count = (int)$xml->stream->comment_count;
	$base_time     = (int)$xml->stream->base_time;
	$open_time     = (int)$xml->stream->open_time;
	$start_time    = (int)$xml->stream->start_time;
	$end_time      = (int)$xml->stream->end_time;
	$archive       = (int)$xml->stream->archive;

	$addr   = (string)$xml->ms->addr;
	$port   = (int)$xml->ms->port;
	$thread = (int)$xml->ms->thread;

	echo "$co\n";
	echo "$title\n";
	echo "$description\n";
	echo "$provider_type\n";
	echo $xml->stream->owner_name."\n";
	echo $xml->user->nickname."(".$xml->user->user_id.")\n";
	echo "addr:[$addr:$port] thread:[$thread]\n";
	echo "watch_count  :".$watch_count."\n";
	echo "comment_count:".$comment_count."\n";
	echo "base_time    :".date("Y-m-d H:i:s", $base_time)."\n";
	echo "open_time    :".date("Y-m-d H:i:s", $open_time)."\n";
	echo "start_time   :".date("Y-m-d H:i:s", $start_time)."\n";
	echo "end_time     :".date("Y-m-d H:i:s", $end_time)."\n";
	echo "archive      :".$archive."\n";

	$fp = fsockopen($addr, $port, $errno, $errstr, 1);
	if($fp === false)
	{
		echo "error $errno:$errstr\n";
		echo "$addr:$port\n";
		exit(1);
	}
	stream_set_timeout($fp, 1);

	$reader = new reader;
	$reader->fp = $fp;

	if($archive == 1)
	{
		$str = "<thread res_from=\"-1000\" version=\"20061206\" scores=\"1\" thread=\"$thread\" />\0";
		fwrite($fp, $str);
		while(1)
		{
			$str = $reader->read();
			$res = put_comment($str);
			if($res === true)break;
		}
	}
	else
	{
		$str = "<thread res_from=\"-1\" version=\"20061206\" scores=\"1\" thread=\"$thread\" />\0";
		fwrite($fp, $str);
		for($i = 0; $i < 64; $i++)
		{
			$str = $reader->read();
			echo "#$str#\n";
		}
	}

	fclose($fp);
?>