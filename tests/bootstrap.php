<?php
////////////////////////////////////////////////////////////////////////////////
// __________ __             ________                   __________              
// \______   \  |__ ______  /  _____/  ____ _____ ______\______   \ _______  ___
//  |     ___/  |  \\____ \/   \  ____/ __ \\__  \\_  __ \    |  _//  _ \  \/  /
//  |    |   |   Y  \  |_> >    \_\  \  ___/ / __ \|  | \/    |   (  <_> >    < 
//  |____|   |___|  /   __/ \______  /\___  >____  /__|  |______  /\____/__/\_ \
//                \/|__|           \/     \/     \/             \/            \/
// -----------------------------------------------------------------------------
//          Designed and Developed by Brad Jones <brad @="bjc.id.au" />         
// -----------------------------------------------------------------------------
////////////////////////////////////////////////////////////////////////////////

/* 
 * Include the robo file so we know what port
 * the built in php server is running on.
 */
require(__DIR__.'/../RoboFile.php');

/*
 * Create the base Guzzle Client we will use for all our acceptance testing.
 * NOTE: We return a new client each time so that we don't have any chance of
 * cross contamination.
 */
function GuzzleTester()
{
	return new GuzzleHttp\Client
	([
		'base_url' => 'http://127.0.0.1:'.RoboFile::$serverPort,
		'defaults' => ['cookies' => true]
	]);
}