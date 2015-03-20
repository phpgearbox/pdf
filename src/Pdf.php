<?php namespace Gears;
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

use SplFileInfo;
use RuntimeException;
use Gears\String as Str;
use Gears\Di\Container;
use Gears\Pdf\TempFile;

class Pdf extends Container
{
	/**
	 * This holds an instance of ```Gears\Pdf\TempFile```
	 * pointing to the document we will convert to PDF.
	 */
	protected $document;

	/**
	 * This holds an instance of ```SplFileInfo```
	 * pointing to the original untouched document.
	 *
	 * OR
	 *
	 * NULL if a HTML string is provided.
	 */
	protected $originalDocument;

	/**
	 * The type of source document we are to convert to PDF
	 * Valid values for this are: ```docx```, ```html```
	 */
	private $documentType;

	/**
	 * This class is simply just a Facade to create a fluent api for the end
	 * user. This class doesn't do any of the actual converting. Based on the
	 * document type it will proxy calls to the appropriate backend class.
	 */
	protected $backend;

	/**
	 * A closure than returns a configured instance of ```SplFileInfo```.
	 */
	protected $injectFile;

	/**
	 * A closure than returns an instance of ```Gears\Pdf\TempFile```.
	 */
	protected $injectTempFile;

	/**
	 * Set Container Defaults
	 *
	 * This is where we set all our defaults. If you need to customise this
	 * container this is a good place to look to see what can be configured
	 * and how to configure it.
	 */
	protected function setDefaults()
	{
		$this->file = $this->protect(function($filePath)
		{
			return new SplFileInfo($filePath);
		});

		$this->tempFile = $this->protect(function($contents, $ext)
		{
			$file = new TempFile('GearsPdf', $ext);

			$file->setContents($contents);

			return $file;
		});
	}

	/**
	 * Performs some intial Setup.
	 *
	 * @param string $document This is either a filepath to a docx or html file.
	 *                         Or it may be a HTML string. The HTML string must
	 *                         contain a valid DOCTYPE.
	 *
	 * @param array $config Further configuration for the di container.
	 *
	 * @throws RuntimeException When not of the correct document type.
	 */
	public function __construct($document, $config = [])
	{
		// Configure the container
		parent::__construct($config);

		// Is the document a file
		if (is_file($document))
		{
			// So that the save method can save the PDF in the same folder as
			// the original source document we need a refrence to it.
			$this->originalDocument = $this->file($document);

			// Grab the files extension
			$ext = $this->originalDocument->getExtension();
			if ($ext !== 'docx' && $ext !== 'html')
			{
				throw new RuntimeException('Must be a DOCX or HTML file.');
			}
			$this->documentType = $ext;

			// Save the document to a new temp file
			// In the case of DOCX files we may make changes to the document
			// before converting to PDF so to keep the API consitent lets create
			// a the temp file now.
			$this->document = $this->tempFile(file_get_contents($document), $ext);
		}

		// Check for a HTML string
		elseif (Str::contains($document, 'DOCTYPE'))
		{
			// Again lets save a temp file
			$this->document = $this->tempFile($document, 'html');

			$this->documentType = 'html';
		}
		else
		{
			throw new RuntimeException('Unrecognised document type!');
		}

		// Now create a new backend
		$class = '\\Gears\\Pdf\\'.ucfirst($this->documentType).'\\Backend';
		$this->backend = new $class($this->document, $config);
	}

	/**
	 * Shortcut Converter
	 *
	 * If all you want to do is convert a document into a pdf,
	 * this is a shortcut method to do just that.
	 *
	 * @param string $document This is either a filepath to a docx or html file.
	 *                         Or it may be a HTML string. The HTML string must
	 *                         contain a valid DOCTYPE.
	 *
	 * @param string $pdf Optionally you may supply an output path of the pdf,
	 *                    if not supplied we will create the PDF in the same
	 *                    folder as the source document with the same filename.
	 *                    If you supplied a HTML string as the document we will
	 *                    return the generated PDF bytes.
	 *
	 * @return mixed SplFileInfo or PDF Bytes
	 */
	public static function convert($document, $pdf = null, $config = [])
	{
		$instance = new static($document, $config);

		if (!is_file($document))
		{
			return $instance->backend->generate();
		}

		return $instance->save($pdf);
	}

	/**
	 * Saves the generated PDF.
	 *
	 * We call the backend class to generate the PDF for us.
	 * Then we attempt to save those bytes to a permanent location.
	 *
	 * @param string $path If not supplied we will create the PDF in the name
	 *                     folder as the source document with the same filename.
	 *
	 * @return SplFileInfo
	 */
	public function save($path = null)
	{
		$pdf = $this->backend->generate();

		// If no output path has been supplied save the file
		// in the same folder as the original template.
		if (is_null($path))
		{
			if (is_null($this->originalDocument))
			{
				// This will be thrown when someone attemtps to use
				// the save method when they have supplied a HTML string.
				throw new RuntimeException
				(
					'You must supply a path for us to save the PDF!'
				);
			}

			$ext = $this->originalDocument->getExtension();
			$path = Str::s($this->originalDocument->getPathname());
			$path = $path->replace('.'.$ext, '.pdf');
		}

		// Save the pdf to the output path
		if (@file_put_contents($path, $pdf) === false)
		{
			throw new RuntimeException('Failed to write to file "'.$path.'".');
		}

		// Return the location of the saved pdf
		return $this->file($path);
	}

	/**
	 * Http Download
	 *
	 * If invoked via Apache, PHP-FPM, etc. You may just want to send the PDF
	 * directly to the browser as a downloadable file. This method will generate
	 * the PDF and send the appropriate headers for you.
	 *
	 * @param string $filename The name of the file that the browser will see.
	 *
	 * @param boolean $exit To ensure no extra content is added to the PDF we
	 *                      will by default die after outputting it. If you want
	 *                      to overide ths behaviour feel free just make sure
	 *                      you don't send any extra bytes otherwise your PDF
	 *                      will be corrupt.
	 */
	public function download($filename = 'download.pdf', $exit = true)
	{
		// Send some headers
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		echo $this->backend->generate();
		if ($exit) exit;
	}

	/**
	 * Http Stream
	 *
	 * Unlike the download method this will stream the PDF to the browser.
	 * ie: It will open inside the browsers PDF reader.
	 *
	 * @param boolean $exit To ensure no extra content is added to the PDF we
	 *                      will by default die after outputting it. If you want
	 *                      to overide ths behaviour feel free just make sure
	 *                      you don't send any extra bytes otherwise your PDF
	 *                      will be corrupt.
	 */
	public function stream($exit = true)
	{
		// Send some headers
		header('Content-Type: application/pdf');
		header('Content-Disposition: inline; filename="stream.pdf"');
		echo $this->backend->generate();
		if ($exit) exit;
	}

	/**
	 * Proxy Calls to Backend
	 *
	 * Once a source document has been supplied and a backend choosen.
	 * This will then proxy any unresolved method calls through to backend
	 * class.
	 *
	 * The user can then perform further configuration and custmoistation to
	 * the backend easily before calling one of the output methods above.
	 *
	 * @param string $name
	 * @param array $args
	 * @return mixed
	 */
	public function __call($name, $args)
	{
		if ($this->offsetExists($name))
		{
			return parent::__call($name, $args);
		}
		else
		{
			if (empty($this->backend))
			{
				throw new RuntimeException('Backend Class not created yet!');
			}

			return call_user_func_array([$this->backend, $name], $args);
		}
	}

	public function __set($name, $value)
	{
		if ($this->offsetExists($name))
		{
			return parent::__set($name, $value);
		}
		else
		{
			if (empty($this->backend))
			{
				throw new RuntimeException('Backend Class not created yet!');
			}

			call_user_func([$this->backend, '__set'], $name, $value);
		}
	}
}
