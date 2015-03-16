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

require('../../vendor/autoload.php');

$doc = new Gears\Pdf('../templates/Convert.docx');
$doc->download();

/*
 * NOTE: The trick I have found to get Unoconv to work with apache it to start
 * a listener via the command line first. For example:
 * 
 * ```
 * unoconv --listener &
 * ```
 * 
 * So on your server you just need to make sure this is started on boot.
 * Maybe i'll get around to writing a init script for it...
 */