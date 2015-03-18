<?php namespace Gears\Pdf\Html;
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
use Gears\Pdf\TempFile;
use Symfony\Component\Process\Process;
use Gears\Pdf\Contracts\Backend as BackendInterface;

class Backend extends Container implements BackendInterface
{
	/**
	 * @var Gears\Pdf\TempFile The document we will convert to PDF.
	 */
	protected $document;
	
	/**
	 * @var string The location of the phantomjs binary.
	 */
	protected $injectBinary;
	
	/**
	 * @var string The location of the phantomjs javascript runner.
	 */
	protected $injectRunner;

	/**
	 * @var Symfony\Component\Process\Process
	 */
	protected $injectProcess;
	
	/**
	 * Set Container Defaults
	 * 
	 * This is where we set all our defaults. If you need to customise this
	 * container this is a good place to look to see what can be configured
	 * and how to configure it.
	 */
	protected function setDefaults()
	{
		$this->binary = function()
		{
			if (is_dir(__DIR__.'/../../../vendor'))
			{
				$bin = __DIR__.'/../../../vendor/bin/phantomjs';
			}
			else
			{
				$bin = __DIR__.'/../../../../bin/phantomjs';
			}
			
			if (!is_executable($bin))
			{
				throw new RuntimeException
				(
					'The phantomjs command ("'.$bin.'") '.
					'was not found or is not executable by the current user! '
				);
			}
			
			return realpath($bin);
		};
		
		$this->runner = __DIR__.'/Phantom.js';

		$this->process = $this->protect(function($cmd)
		{
			return new Process($cmd);
		});
		
		$this->tempFile = $this->protect(function()
		{
			return new TempFile('GearsPdf', 'pdf');
		});
	}
	
	/**
	 * Configures this container.
	 * 
	 * @param TempFile $document The html file we will convert.
	 * 
	 * @param array $config Further configuration for this container.
	 */
	public function __construct(TempFile $document, $config = [])
	{
		parent::__construct($config);
		
		$this->document = $document;
	}
	
	/**
	 * Generates the PDF from the HTML File
	 * 
	 * @return PDF Bytes
	 */
	public function generate()
	{
		$output_document = $this->tempFile();
		
		// Build the cmd to run
		$cmd =
			$this->binary.' '.
			$this->runner.' '.
			'"file://'.$this->document->getPathname().'" '.
			$output_document->getPathname()
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
		
		return $output_document->getContents();
	}
}