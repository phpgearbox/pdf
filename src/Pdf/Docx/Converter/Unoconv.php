<?php namespace Gears\Pdf\Docx\Converter;
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
use Gears\Di\Container;
use Gears\String as Str;
use Gears\Pdf\TempFile;
use Symfony\Component\Process\Process;
use Gears\Pdf\Contracts\DocxConverter;

class Unoconv extends Container implements DocxConverter
{
	/**
	 * Property: binary
	 * =========================================================================
	 * This stores the location of the unoconv binary on the local system.
	 */
	protected $injectBinary;

	/**
	 * Property: profile
	 * =========================================================================
	 * This stores the location of where unoconv will use as a user profile.
	 */
	protected $injectProfile;

	/**
	 * Property: process
	 * =========================================================================
	 * This will return a configured instance of
	 * ```Symfony\Component\Process\Process```
	 */
	protected $injectProcess;

	/**
	 * Method: setDefaults
	 * =========================================================================
	 * This is where we set all our defaults. If you need to customise this
	 * container this is a good place to look to see what can be configured
	 * and how to configure it.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	protected function setDefaults()
	{
		$this->binary = '/usr/bin/unoconv';

		$this->profile = '/tmp/gears-pdf-unoconv';

		$this->process = $this->protect(function($cmd)
		{
			return new Process($cmd);
		});
	}

	/**
	 * Method: convertDoc
	 * =========================================================================
	 * This is where we actually do some converting of docx to pdf.
	 * We use the command line utility unoconv. Which is basically a slightly
	 * fancier way of using OpenOffice/LibreOffice Headless.
	 * 
	 * See: http://dag.wiee.rs/home-made/unoconv/
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $docx: This must be an instance of ```SplFileInfo```
	 *           pointing to the document to convert.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function convertDoc(TempFile $docx)
	{
		if (!is_executable($this->binary))
		{
			throw new RuntimeException
			(
				'The unoconv command was not found or is not executable! '.
				'This class uses unoconv to create the PDFs.'
			);
		}

		// Check to see if the profile dir exists and is writeable
		if (is_dir($this->profile) && !is_writable($this->profile))
		{
			throw new RuntimeException
			(
				'If unoconv does not have permissions to the User '.
				'Profile directory ("'.$this->profile.'") the conversion '.
				'will fail!'
			);
		}

		// Build the unoconv cmd
		$cmd =
			'export HOME='.$this->profile.' && '.
			$this->binary.' '.
			'--stdout '.
			'-f pdf '.
			'"'.$docx->getPathname().'"'
		;

		// Run the command
		$process = $this->process($cmd);
		$process->run();

		// Check for errors
		$error = null;

		if (!$process->isSuccessful())
		{
			$error = $process->getErrorOutput();

			// NOTE: For some really odd reason the first time the command runs
			// it does not complete successfully. The second time around it
			// works fine. It has something to do with the homedir setup...
			if (Str::contains($error, 'Error: Unable to connect'))
			{
				$process->run();

				if (!$process->isSuccessful())
				{
					$error = $process->getErrorOutput();
				}
				else
				{
					$error = null;
				}
			}

			if (!is_null($error)) throw new RuntimeException($error);
		}

		// Clean up after ourselves
		exec('rm -rf '.$this->profile);

		// Return the pdf data
		return $process->getOutput();
	}
}
