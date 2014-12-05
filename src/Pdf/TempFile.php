<?php namespace Gears\Pdf;
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

use RuntimeException;
use SplFileInfo as NativeSplFileInfo;

class TempFile extends NativeSplFileInfo
{
	/**
	 * Method: __construct
	 * =========================================================================
	 * We overload the parent constructor to auto generate a new temp filename.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $prefix: The prefix of the generated temporary filename. 
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function __construct($prefix = null)
	{
		if (($temp = tempnam(sys_get_temp_dir(), $prefix)) === false)
		{
			throw new RuntimeException
			(
				'Could not create temporary file with unique name '.
				'in your systems default temporary directory.'
			);
		}

		parent::__construct($temp);
	}
}