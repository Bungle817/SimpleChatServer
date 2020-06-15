#!/usr/bin/php
<?php

/**
 * This is an example Interverse server using Reactphp
 *
 * @copyright 2019 AmandaJones - AltAway Project
 */

// constants. These would usually be within a config file.

	define('RESOURCES','./client');		// Third party data				e.g. c:\wa24\
	define('CLIENTVERSION','');			// optional specific version. gets 'mag' appended
	define('LISTENIP','0.0.0.0');		// 0.0.0.0 for all network interfaces
	define('WORLDPORT', 17872);			// tcp port number

	define('MAXUSERS',10);
	define('DEBUG',0);

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Socket\ConnectionInterface;
use React\Socket\LimitingServer;

require __DIR__ . '/vendor/autoload.php';

include('Trans.php');

Trans::initialise(RESOURCES, CLIENTVERSION);


// fire up ReactPHP
$loop = Factory::create();

$userServer = new Server(constant('LISTENIP').':'.constant('WORLDPORT'), $loop);
$userServer = new LimitingServer($userServer, MAXUSERS);

$users = array();
$usercount = 0;

$userServer->on('connection', function (ConnectionInterface $client) use ($userServer, $loop, &$users, &$usercount) {

	$id_user = $usercount;
	$usercount ++;
	$users[$id_user] = array('name' => 'Demo '.$id_user );
	
    $client->on('data', function ($data) use ($client, $userServer, $loop, &$users, $id_user) {
		$users[$id_user];
		while (strlen($data)) { 
			$cmd = unpack('nnoid/ncmdNum/NdataLen', $data);
			$params = substr($data,8, $cmd['dataLen']);
			// unpack parameters
			$p = Trans::clientToCmd($cmd['cmdNum'],$params); 
			switch ($cmd['cmdNum']) {
				case 19:		// idler
					break;
				case 11:		// login request from server
					// accept anything!
					
					// Client needs these.
					send($client, 10, 16, array('count' => 0, 'size' => 0));	// resource update start
					send($client, 10, 18, array());							// resource update end

					// this would present the user with a list of pre-exsting avatars
					$params = array(	'success' => 0,
						'newAvatarAllowed' => 1,
						'numAvatars' => 0,
						'maxAvatars' => 1,
						'avatarsInuse' => 0,
						'dummyfielda' => 0,
						'avatarList' => '',
						'ignoreScope' => 1);

					send($client, 10, 12, $params);
					break;
				
				case 13:		// avatar selection
					// accept anything!

					// create a random avatar
					$noid = 30+2*$id_user;
					$users[$id_user]['avatar'] = 
						array('noid' => $noid, 'body' => rand(6,7), 'head' => rand(8,13),
								'position' => array(rand(50,450), rand(10,450), 0)
						);
					
					// Alert user as to new locale ..
					send($client, 0, 100, array());
					// send room and other users
					send($client, 0, 101, getRoom($users, $noid)); 

					// send ourself
					$av = array($noid => getBody($users[$id_user]), $noid+1 => getHead($users[$id_user]));
					$actor=packObjects($av);
					$params = array('entryMsg' => 'Welcome to the demo chat server!',
								'cvsize' =>strlen($actor),
								'contentsVector' => $actor);
					send($client, 0, 104, $params);
					// let everybody else know we've arrived
					unset($params['entryMsg']);
					sendToRoom($userServer, $client, 0, 104, $params);
					break;
				
				case 72:		// speak
					$noid = $users[$id_user]['avatar']['noid'];
					$p['success'] = 0;
					$p['speaker'] = $noid;

					send($client, $noid, 73, $p);
					sendToRoom($userServer, $client, $noid, 74, $p);
					break;
					
				case 1570:		// walk
					$noid = $users[$id_user]['avatar']['noid'];
					send($client, $noid, 1571, $p);
					sendToRoom($userServer, $client, $noid, 1572, $p);
					$users[$id_user]['avatar']['position'] = array($p['coords1'],$p['coords2'],$p['coords3']);
					break;
				
				default:		// unsuported
					send($client, $noid, 70, array('success' => 1));
				
			}


			$data = substr($data, 8 + $cmd['dataLen']);
		}
    });	
	
	
		$client->on('close', function () use ($client, $userServer, &$users, $id_user) {
		if (isset($users[$id_user])){
			$params=array('actor'=> $users[$id_user]['avatar']['noid']);
			sendToRoom($userServer, $client, 0, 105, $params);
		}
		unset($users[$id_user]);
		$client->end();
	});

	
});


echo 'Listening on ' . $userServer->getAddress() . PHP_EOL;
$loop->run();
exit;

 function send($client, $noid, $ccode, $data)
{
	if (is_array($data)) {
		$data = Trans::cmdToClient($ccode,$data);
	}
    $len = strlen($data);
	$buf = pack("nnNa{$len}", $noid, $ccode, $len, $data);
	$client->write($buf);
	return true;
}

function sendToRoom($server, $except, $noid, $ccode, $data) {
	foreach ($server->getConnections() as $connection) {
		if ($connection != $except) {
			send($connection, $noid, $ccode, $data);
		}
	}
}




function getRoom ($users, $noid) {
	
	$params = array('helper_name' => 'Helper', 'guide_name' => 'Guide', 'wizard_name' => 'Dog',
		'actor_noid' => $noid,
		'actor_flags' => 0,
		'actor_perm_level' => 0,
		'actor_perm_flags' => 8388592);

	$room = getRoomDeliveryData($users, $noid);
	$ucl=strlen($room);
	$croom = pack('N',$ucl) . ffcompress($room);
	$params['cvsize'] = strlen($croom);
	$params['contentsVector'] = $croom;

	return $params;
}
	
function getRoomDeliveryData(&$users, $except) {
	
// This defines the actual room -
$objects = array( 0=> array(	'name' => 'Chat Room',
						'classNumber' => 20,
						'max_avatars'=>10,
							'common_flags' => 3,
							'max_ghosts' => -1,
							'horizon' => 75,
							'position1' => 100,
							'position2' => 10,
							'position3' => 1,
							'areaCode' => 0,
							'address' => '',
							'region_flags' => 0),
					15 => array( 'name' => 'Ghost',
							'classNumber' => 22,
							'position1' => -500,
							'position2' => 0,
							'position3' => 0,
///							'imageURL' => 'o.GHOST.DIR.img',
							'common_flags' => 3	),

					14 => array(	'name' => 'Quip',
							'classNumber' => 23,
							'position1' => 0,
							'position2' => 0,
							'position3' => 288,
							'common_flags' => 3,
//							'imageURL' => 'o.quips1.img',
							'imageDefault' => 4,
							'choreState' => 5), 
					20 => array(	'name' => 'Floor',
							'classNumber' => 25,
							'position1' => 0,
							'position2' => 0,
							'position3' => 1,
							'common_flags' => 6,
//							'imageURL' => 'o.plainwall3.img',
							'flatType' => 0,
							'imageDefault' => 15,
							'choreState' => 0),
					21 => array(	'name' => 'Wall',
							'classNumber' => 25,
							'position1' => 0,
							'position2' => 85,
							'position3' => 0,
							'common_flags' => 6,
							//'imageURL' => 'o.plainwall3.img',
							'flatType' => 1,
							'imageDefault' => 14,
							'choreState' => 0)
				);

		// add each pre-existing user to this.
		$i = 0;
		foreach ($users as &$u) {
			$noid = $u['avatar']['noid'];
			if ( $noid != $except) { 
				$objects[$noid] = getBody($u);
				$objects[$noid+1] = getHead($u);
			}
		}
		return packObjects($objects);
}


function packObjects($objects) {
		// build the binary data stream
		$room = '';
		foreach ($objects as $k => $o) {
			$o['noid'] = $k;
			$block = Trans::objectToClient($o['classNumber'],$o);
			$room .= pack('n',strlen($block)).$block;
		}
		return $room;
}
	

function getBody($u) {
	$noid = $u['avatar']['noid'];
	return	array('container_noid' => 0,
		'noid' => $noid,
		'classNumber' => 21,
		'name' => $u['name'],
		'position1' => $u['avatar']['position'][0],
		'position2' => $u['avatar']['position'][1],
		'position3' => $u['avatar']['position'][2],
		'mood' => 1,
		'imageDefault' => $u['avatar']['body'],
		'reducedCapacity' => 10);
}

function getHead($u) {
	$noid = $u['avatar']['noid'];
	return array('container_noid' => $noid,
		'noid' => $noid +1,
		'classNumber' => 24,
		'name' => 'Head',
		'position1' => 1,
		'position2' => 0,
		'position3' => 0,
		'imageDefault' => $u['avatar']['head'],
		'colorMap' => 'NLKJIHGFED-,+#*)',
		'reducedCapacity' => 3);
}

function ffcompress($data){
	$d = '';
	for ($i = 0; $i<strlen($data); $i++) {
		$c = $data[$i];
		switch (ord($c)) {
			case 255:
				$c .=$c;
				break;
			case 0:
				$j = 1;
				while ($i+$j < strlen($data) && $data[$i+$j] == chr(0)) {
					$j++;
				}
				if ($j > 1) {
					$c = chr(255) . chr($j);
					$i	 = $i + $j -1;
				}
				break;
			default:
				;
		} // switchif ($c == chr(255)) {
		$d .= $c;
	}
	return $d;

}
