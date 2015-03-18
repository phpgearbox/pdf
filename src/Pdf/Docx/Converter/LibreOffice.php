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

class LibreOffice extends Container implements DocxConverter
{
	/**
	 * Property: binary
	 * =========================================================================
	 * This stores the location of the libreoffice binary on the local system.
	 */
	protected $injectBinary;

	/**
	 * Property: profile
	 * =========================================================================
	 * This stores the location of where libreoffice will create a temp user
	 * profile. I guess we could use the the current users profile however when
	 * used via Apache or another webserver this isn't possible.
	 */
	protected $injectProfile;

	/**
	 * Property: output
	 * =========================================================================
	 * Unlike unoconv libreoffice headless does not provide the ability to pipe
	 * the generated pdf to stdout, instead it allows us to set an output folder
	 * for where the generated PDFs will be saved.
	 */
	protected $injectOutput;

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
		$this->binary = '/usr/bin/libreoffice';

		$this->profile = '/tmp/gears-pdf-libreoffice';

		$this->output = '/tmp/gears-pdf-libreoffice/generated';

		$this->process = $this->protect(function($cmd)
		{
			return new Process($cmd);
		});
	}

	/**
	 * Method: convertDoc
	 * =========================================================================
	 * This is where we actually do some converting of docx to pdf.
	 * This converter uses the OpenOffice/LibreOffice Headless capabilities.
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
				'The libreoffice command ("'.$this->binary.'") '.
				'was not found or is not executable by the current user! '
			);
		}

		// Check to see if the profile dir exists and is writeable
		if (is_dir($this->profile) && !is_writable($this->profile))
		{
			throw new RuntimeException
			(
				'If libreoffice does not have permissions to the User '.
				'Profile directory ("'.$this->profile.'") the conversion '.
				'will fail!'
			);
		}

		// Build the cmd to run
		$cmd =
			$this->binary.' '.
			'--headless '.
			'-env:UserInstallation=file://'.$this->profile.' '.
			'--convert-to pdf:writer_pdf_Export '.
			'--outdir "'.$this->output.'" '.
			'"'.$docx->getPathname().'"'
		;

		// Run the command
		$process = $this->process($cmd);
		$process->run();

		// Check for errors
		if (!$process->isSuccessful())
		{
			throw new RuntimeException
			(
				$process->getErrorOutput()
			);
		}

		// Grab the generated pdf
		$pdf = file_get_contents
		(
			$this->output.'/'.$docx->getBasename('.docx').'.pdf'
		);

		// Clean up after ourselves
		exec('rm -rf '.$this->profile);

		// Finally return the generated pdf
		return $pdf;
	}
}
