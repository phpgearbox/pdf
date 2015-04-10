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
use Gears\String as Str;
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
	 * @var array This will passed through to phantomjs.
	 * @see http://phantomjs.org/api/webpage/property/paper-size.html
	 */
	protected $paperSize;

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
	 * @var string Code that we will inject into the html document.
	 */
	protected $injectPrintFramework;

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

		$this->printFramework = function()
		{
			return
			'
				<style>'.file_get_contents(__DIR__.'/Print.css').'</style>
				<script>'.file_get_contents(__DIR__.'/isVisible.js').'</script>
				<script>'.file_get_contents(__DIR__.'/jQuery.js').'</script>
				<script>'.file_get_contents(__DIR__.'/Print.js').'</script>
			';
		};
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
		// Inject our print framework
		if ($this->printFramework !== false)
		{
			$this->document->setContents
			(
				Str::s($this->document->getContents())->replace
				(
					'</head>',
					$this->printFramework.'</head>'
				)
			);
		}

		// Create a new temp file for the generated pdf
		$output_document = $this->tempFile();

		// Build the cmd to run
		$cmd =
			$this->binary.' '.
			$this->runner.' '.
			'--url "file://'.$this->document->getPathname().'" '.
			'--output "'.$output_document->getPathname().'" '
		;

		if (isset($this->paperSize['width']))
		{
			$cmd .= '--width "'.$this->paperSize['width'].'" ';
		}

		if (isset($this->paperSize['height']))
		{
			$cmd .= '--height "'.$this->paperSize['height'].'" ';
		}

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

		// Return the pdf
		return $output_document->getContents();
	}

	/**
	 * Paper Size Setter
	 *
	 * It is easier to work with numbers, so while I understand phantomjs does
	 * have it's own paper size format and orientation settings. Here we provide
	 * a basic lookup table to convert A4, A3, Letter, etc...
	 * Into their metric counterparts.
	 *
	 * @param array $size
	 *
	 * @return self
	 */
	public function setPaperSize($size)
	{
		if (isset($size['format']))
		{
			switch (strtolower($size['format']))
			{
				case 'a0':
					$size['width'] = '841mm';
					$size['height'] = '1189mm';
				break;

				case 'a1':
					$size['width'] = '594mm';
					$size['height'] = '841mm';
				break;

				case 'a2':
					$size['width'] = '420mm';
					$size['height'] = '594mm';
				break;

				case 'a3':
					$size['width'] = '297mm';
					$size['height'] = '420mm';
				break;

				case 'a4':
					$size['width'] = '210mm';
					$size['height'] = '297mm';
				break;

				case 'a5':
					$size['width'] = '148mm';
					$size['height'] = '210mm';
				break;

				case 'a6':
					$size['width'] = '105mm';
					$size['height'] = '148mm';
				break;

				case 'a7':
					$size['width'] = '74mm';
					$size['height'] = '105mm';
				break;

				case 'a8':
					$size['width'] = '52mm';
					$size['height'] = '74mm';
				break;

				case 'a9':
					$size['width'] = '37mm';
					$size['height'] = '52mm';
				break;

				case 'a10':
					$size['width'] = '27mm';
					$size['height'] = '37mm';
				break;

				case 'letter':
					$size['width'] = '216mm';
					$size['height'] = '280mm';
				break;

				case 'legal':
					$size['width'] = '215.9mm';
					$size['height'] = '255.6mm';
				break;

				default:
					throw new RuntimeException('Unregcognised Paper Size!');
				break;
			}

			// Swap the dimensions around if landscape
			if (isset($size['orientation']))
			{
				if ($size['orientation'] == 'landscape')
				{
					$width = $size['width'];
					$height = $size['height'];
					$size['width'] = $height;
					$size['height'] = $width;
				}
			}
		}

		$this->paperSize = $size;

		return $this;
	}
}
