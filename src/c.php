<?php
DEFINE("HELP", "
		Command to call a contact searching by name
		it's like this but easier to remember
			termux-contact-list | tr '[:upper:]' '[:lower:]'\
					| jq -c '.[] | select(.name | contains(\"john\"))'\
					| jq -s unique | jq -c '.[] | select(.name | contains(\"doe\"))' ... so on
	Usage:
			pcall john doe -c -f -j
				will (-c)all the (-f)irst john doe on your list and output info in (-j)son format.
			pcall john doe 551
				will show john doe with 551 on the name/number, useful if there are more than one John Doe.
			pcall john doe 551 -c
				will call john doe contact which has a 551 on the name/number.
			All human output goes to stderr and json output goes to stdout useful if you want to pipe it to jq to prettyfy or extra processing.
");
	require __DIR__ ."/vendor/autoload.php";
	$opts = new Commando\Command();

// Functions
function arrSearch($hayStack, $needle){
	$o = array_filter($hayStack, function($el) use ($needle) {
		return ( stripos($el['name'], $needle) !== false || stripos($el['number'], $needle) !== false );
	});
	return $o;
}
function showList($arr){
	$o="";	
	foreach($arr as $k=>$eachArr){
			$o.=print_r($eachArr, true);
	}
	$o = filter($o);
	echo PHP_EOL.$o;
}
function filter($contents){
	$cmd = "echo \"$contents\" | grep -e \"\"";
	$exe = trim(`$cmd`);
	return $exe;
}
function showJson($arr){
	print_r(json_encode($arr));
}

function show($arr){
	global $opts;
//	if (!$opts['json']){
	if (false){
		showList($arr);
	}else{
		showJson($arr);
	}
}
	$opts->option('h')->aka('help2')->describedAs(HELP)->boolean()->defaultsTo(false);
	$opts->option('c')->aka('call')->describedAs('If there is one match, call him/her.')->boolean()->defaultsTo(false);
	$opts->option('f')->aka('callthefirst')->describedAs('Call the first match')->boolean()->defaultsTo(false);
	$opts->option('p')->aka('prefix')->describedAs('A dial prefix (optional)')->defaultsTo("");
	$opts->option('s')->aka('simcall')->describedAs('Simulate call')->boolean()->defaultsTo(false);
	$opts->option('v')->aka('version')->describedAs('Version 0.99a')->boolean()->defaultsTo(false);
	// $opts->option('j')->aka('json')->describedAs('Output in json format')->boolean()->defaultsTo(false);

	$numArgs=0;
	for ($i=0;$i<=15;$i++){
		if ($opts[$i]=="")
			break;
		// echo "$i:".$opts[$i].PHP_EOL;
		$numArgs=$i+1;
	}
	$f = trim(`termux-contact-list| tr '[:upper:]' '[:lower:]'`);
	$arrCL = json_decode($f, true);
	// remove repeats 
	// print_r($arrCL);
	$arrCLU = array_unique($arrCL, SORT_REGULAR);
  // print_r($arrCLU);
	$o = $arrCLU;
	for($i=0;$i<$numArgs;$i++){
		$o = arrSearch($o, $opts[$i]);
	}
	// print_r($o);
	switch(sizeOf($o))
	{
		case "1":
			show($o);
			if ($opts['call']){
				if (strlen($opts['prefix'])>0)			
					echo "dialing with prefix: ".$opts['prefix'].PHP_EOL;
				$extract = array_pop($o);
				$cmd = "termux-call ".$opts['prefix'].str_replace(" ","", $extract['number']);
				if ($opts['simcall'])
					echo "simulating: $cmd".PHP_EOL;
				else{
					echo $cmd;
					$exe = trim(`$cmd`);
				}
			}
			break;
		case "0":
			if (($numArgs)>2){
				$u['status']="No matches, try fewer terms";
				fwrite(STDERR, "No matches, try fewer terms".PHP_EOL);
				show($u);
			}else{
				$u['status']="No matches";
				fwrite(STDERR, "No matches".PHP_EOL);
				show($u);
			}
			exit(2);
			break;
		default:
			if ($opts['callthefirst']){
				fwrite(STDERR, "calling first contact on list".PHP_EOL);		
				$u = array_shift($o);
				fwrite(STDERR, "calling... {$u['name']} : {$u['number']}".PHP_EOL);
				$u['status']="calling";
				$u['otherContacts']=$o;
				$cmd = "termux-call ".$opts['prefix'].str_replace(" ","", $u['number']);
				if ($opts['simcall'])
					fwrite(STDERR, "simulating: $cmd".PHP_EOL);
				else{
					fwrite(STDERR, "$cmd".PHP_EOL);
					$exe = trim(`$cmd`);
				}
				show($u);
			}else{
				$u['status']="Too many matches";
				$u['otherContacts']=$o;
				show($u);
				fwrite(STDERR, "Too many matches".PHP_EOL);
			}
			exit(1);
			break;
	}


