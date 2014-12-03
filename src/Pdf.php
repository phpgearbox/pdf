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

use ZipArchive;
use SplFileInfo;
use SimpleXMLElement;
use RuntimeException;
use Gears\String as Str;
use Gears\Di\Container;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;

class Pdf extends Container
{
	/**
	 * Property: template
	 * =========================================================================
	 * Location to the docx document to use as the template for our PDF.
	 * Set as the first argument of the constructor of this class.
	 */
	protected $template;

	/**
	 * Property: tempDocument
	 * =========================================================================
	 * This will hold an instance of ```SplFileInfo```
	 * pointing to the copy of [Property: template](#).
	 */
	protected $tempDocument = false;

	/**
	 * Property: documentXML
	 * =========================================================================
	 * This is where store the main ```word/document.xml``` of the docx file.
	 * It will be an instance of ```SimpleXMLElement```.
	 */
	protected $documentXML;

	/**
	 * Property: headerXMLs
	 * =========================================================================
	 * This is where store any header xml. ie: ```word/header1.xml```.
	 * It will contains instances of ```SimpleXMLElement```.
	 */
	protected $headerXMLs = [];

	/**
	 * Property: footerXMLs
	 * =========================================================================
	 * This is where store any footer xml. ie: ```word/footer1.xml```.
	 * It will contains instances of ```SimpleXMLElement```.
	 */
	protected $footerXMLs = [];

	/**
	 * Property: fileSystem
	 * =========================================================================
	 * An instance of ```Symfony\Component\Filesystem\Filesystem```.
	 */
	protected $injectFileSystem;

	/**
	 * Property: zip
	 * =========================================================================
	 * An instance of ```ZipArchive```.
	 */
	protected $injectZip;

	/**
	 * Property: unoconvBin
	 * =========================================================================
	 * This will store the location to the unoconvBin binary.
	 */
	protected $injectUnoconvBin;

	/**
	 * Property: fileInfo
	 * =========================================================================
	 * A closure than returns a configured instance of ```SplFileInfo```.
	 */
	protected $injectFileInfo;

	/**
	 * Property: process
	 * =========================================================================
	 * A closure that returns an instance of
	 * ```Symfony\Component\Process\Process```
	 */
	protected $injectProcess;

	/**
	 * Property: xml
	 * =========================================================================
	 * A closure that returns an instance of
	 * ```SimpleXMLElement```
	 */
	protected $injectXml;

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
		$this->fileSystem = function()
		{
			return new Filesystem;
		};

		$this->zip = function()
		{
			return new ZipArchive;
		};

		$this->fileInfo = $this->protect(function($filePath)
		{
			return new SplFileInfo($filePath);
		});

		$this->process = $this->protect(function($cmd)
		{
			return new Process($cmd);
		});

		$this->xml = $this->protect(function($xml)
		{
			return new SimpleXMLElement($xml);
		});

		$this->unoconvBin = '/usr/bin/unoconv';
	}

	/**
	 * Method: __construct
	 * =========================================================================
	 * Performs some intial setup so we can convert the docx document to a PDF.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $template: The filepath to a docx template.
	 *  - $config: Further configuration for the di container.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 *  - RuntimeException: Not of the correct template type.
	 */
	public function __construct($template, $config = [])
	{
		parent::__construct($config);

		$this->template = $this->fileInfo($template);

		if ($this->template->getExtension() !== 'docx')
		{
			throw new RuntimeException
			(
				'Template must be an Open XML Document or docx file. '.
				'This package does not work with the Open Document Format odt.'
			);
		}

		if (!is_executable($this->unoconvBin))
		{
			throw new RuntimeException
			(
				'The unoconv command was not found or is not executable! '.
				'This package uses unoconv to create the PDFs.'
			);
		}
	}

	/**
	 * Method: convert
	 * =========================================================================
	 * If all you want to do is convert a docx document into a pdf document.
	 * This is a shortcut method to do just that.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $docx: This is the path to the source document.
	 * 
	 *  - $pdf: Optionally you may supply an output path of the pdf,
	 *    if not supplied we will create the PDF in the name folder as
	 *    the source document with the same filename.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * An instance of of ```SplFileInfo``` pointing to the pdf document.
	 */
	public static function convert($docx, $pdf = null)
	{
		$instance = new static($docx);
		return $instance->save($pdf);
	}

	/**
	 * Method: save
	 * =========================================================================
	 * To save the document, simply call this method.
	 * 
	 * This is where we run the unoconv command and
	 * actually covert the docx document to a pdf.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $path: Optionally you may supply an output path of the pdf,
	 *    if not supplied we will create the PDF in the name folder as
	 *    the source document with the same filename.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * ```SplFileInfo```
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 *  - RuntimeException: If a path to the temp file could not be created.
	 */
	public function save($path = null)
	{
		if ($this->tempDocument === false)
		{
			// If some one is just doing a simple conversion
			// we can just use the original template document.
			$doc = $this->template;
		}
		else
		{
			// Make sure we save the searched and replaced version of the doc.
			$doc = $this->tempDocument;
			$this->writeTempDocument();

			if (is_null($path))
			{
				$path = Str::s($this->template->getPathname());
				$path = $path->replace('.docx', '.pdf');
			}
		}

		$path = $this->fileInfo($path);

		// Build the unoconv cmd
		$cmd = 'export HOME=/tmp && '.$this->unoconvBin.' -v -f pdf';
		if (!is_null($path)) $cmd .= ' --output="'.$path->getPathname().'"';
		$cmd .= ' "'.$doc->getPathname().'"';

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

		// Delete the temp document if it exists.
		if ($this->tempDocument !== false) $this->deleteTempDocument();

		// Parse the outputted file path
		$output_file = $this->fileInfo(Str::between
		(
			$process->getOutput(), 'Output file: ', "\n"
		));

		// Sometimes on some installations of unoconv it doesn't save the file
		// at the expected location, it instead saves the final file inside a
		// folder with the same name as the file. The following corrects this.
		if ($output_file->getPathname() != $path->getPathname())
		{
			$temp = $this->fileInfo(tempnam(sys_get_temp_dir(), 'GearsPdf'));
			$this->fileSystem->copy($output_file, $temp, true);
			$this->fileSystem->remove($output_file->getPath());
			$this->fileSystem->copy($temp, $path, true);
			$this->fileSystem->remove($temp);
		}

		// Return the location of the saved pdf
		return $path;
	}

	/**
	 * Method: download
	 * =========================================================================
	 * Instead of saving the pdf, perhaps you just want to send it directly
	 * to the browser as a down-loadable file. This method will generate the
	 * PDF and send the appropriate headers for you.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function download()
	{
		// TODO
	}

	/**
	 * Method: stream
	 * =========================================================================
	 * Unlike the download method this will stream the PDF to the browser.
	 * ie: It will open inside the browsers PDF reader.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function stream()
	{
		// TODO
	}

	/**
	 * Method: setValue
	 * =========================================================================
	 * Set a Template value.
	 * 
	 * This will search through all headers and footers
	 * as well as the main document body.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $search: The tag name to search for.
	 *  - $replace: The value to replace the tag with.
	 *  - $limit: How many times to search for the tag.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function setValue($search, $replace, $limit = -1)
	{
		if ($this->tempDocument === false) $this->readTempDocument();

		foreach ($this->headerXMLs as $index => $headerXML)
		{
			$this->headerXMLs[$index] = $this->setValueForPart
			(
				$this->headerXMLs[$index],
				$search,
				$replace,
				$limit
			);
		}

		$this->documentXML = $this->setValueForPart
		(
			$this->documentXML,
			$search,
			$replace,
			$limit
		);

		foreach ($this->footerXMLs as $index => $headerXML)
		{
			$this->footerXMLs[$index] = $this->setValueForPart
			(
				$this->footerXMLs[$index],
				$search,
				$replace,
				$limit
			);
		}
	}

	/**
	 * Method: cloneBlock
	 * =========================================================================
	 * Clones a block.
	 * 
	 * > NOTE: Currently only works in the main body content.
	 * > Will not work in headers and footers.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $blockname: The name of the block to clone.
	 *  - $clones: How many times do we want to clone the block.
	 *  - $replace: Whether or not to replace the original block with the clones
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function cloneBlock($blockname, $clones = 1, $replace = true)
	{
		if ($this->tempDocument === false) $this->readTempDocument();

		$matches = $this->searchForBlock($this->documentXML, $blockname);

		if (isset($matches[1]))
		{
			// The xml block to be cloned
			$clone = $matches[1];

			// An array of the cloned blocks of xml
			$cloned = [];

			for ($i = 1; $i <= $clones; $i++)
			{
				// For all tags inside the block we will add an
				// incrementing integer to the end of the tag name.
				$cloned[] = preg_replace('/\${(.*?)}/','${$1_'.$i.'}', $clone);
			}

			if ($replace)
			{
				$this->documentXML = $this->xml(str_replace
				(
					$matches[0],
					implode('', $cloned),
					$this->documentXML->asXml()
				));
			}
		}
	}

	/**
	 * Method: replaceBlock
	 * =========================================================================
	 * Replaces a block. This can be used to perform very low level editing to
	 * the document. The idea being that you will need to actually provide valid
	 * DOCx XML as the replacement string.
	 * 
	 * > NOTE: Currently only works in the main body content.
	 * > Will not work in headers and footers.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $blockname: The name of the block to replace.
	 *  - $replacement: The XML to insert into the document.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function replaceBlock($blockname, $replacement)
	{
		if ($this->tempDocument === false) $this->readTempDocument();

		$matches = $this->searchForBlock($this->documentXML, $blockname);

		if (isset($matches[1]))
		{
			$this->documentXML = $this->xml(str_replace
			(
				$matches[0],
				$replacement,
				$this->documentXML->asXml()
			));
		}
	}

	/**
	 * Method: deleteBlock
	 * =========================================================================
	 * Delete a block of text.
	 * 
	 * > NOTE: Currently only works in the main body content.
	 * > Will not work in headers and footers.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $blockname: The blockname to remove.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function deleteBlock($blockname)
	{
		$this->replaceBlock($blockname, '');
	}

	/**
	 * Method: cloneRow
	 * =========================================================================
	 * Clone a table row in a template document.
	 * 
	 * > NOTE: Currently only works in the main body content.
	 * > Will not work in headers and footers.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $search:
	 *  - $numberOfClones:
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * 
	 */
	public function cloneRow($search, $numberOfClones)
	{
		if ($this->tempDocument === false) $this->readTempDocument();

		$search = $this->normaliseStartTag($search);

		$xml = $this->documentXML->asXml();

		if (($tagPos = strpos($xml, $search)) === false)
		{
			throw new RuntimeException
			(
				'Can not clone row, template variable not found '.
				'or variable contains markup.'
			);
		}

		$rowStart = $this->findRowStart($xml, $tagPos);
		$rowEnd = $this->findRowEnd($xml, $tagPos);
		$xmlRow = Str::slice($xml, $rowStart, $rowEnd);

		// Check if there's a cell spanning multiple rows.
		if (preg_match('#<w:vMerge w:val="restart"/>#', $xmlRow))
		{
			// $extraRowStart = $rowEnd;
			$extraRowEnd = $rowEnd;

			while (true)
			{
				$extraRowStart = $this->findRowStart($xml, $extraRowEnd + 1);
				$extraRowEnd = $this->findRowEnd($xml, $extraRowEnd + 1);

				// If extraRowEnd is lower then 7, there was no next row found.
				if ($extraRowEnd < 7) break;

				// If tmpXmlRow doesn't contain continue,
				// this row is no longer part of the spanned row.
				$tmpXmlRow = Str::slice($xml, $extraRowStart, $extraRowEnd);
				if
				(
					!preg_match('#<w:vMerge/>#', $tmpXmlRow) &&
					!preg_match('#<w:vMerge w:val="continue" />#', $tmpXmlRow)
				){
					break;
				}

				// This row was a spanned row,
				// update $rowEnd and search for the next row.
				$rowEnd = $extraRowEnd;
			}

			$xmlRow = Str::slice($xml, $rowStart, $rowEnd);
		}

		$result = Str::slice($xml, 0, $rowStart);

		for ($i = 1; $i <= $numberOfClones; $i++)
		{
			$result .= preg_replace('/\$\{(.*?)\}/', '\${\\1_' . $i . '}', $xmlRow);
		}

		$result .= Str::slice($xml, $rowEnd);

		$this->documentXML = $this->xml($result);
	}

	/**
	 * Method: findRowStart
	 * =========================================================================
	 * Find the start position of the nearest table row before $offset.
	 * Used by [Method: cloneRow](#)
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $xml: The xml string to work with. __STRING not SimpleXMLElement__
	 *  - $offset: The offset
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * int
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 *  - RuntimeException: When start position not found.
	 */
	protected function findRowStart($xml, $offset)
	{
		$rowStart = strrpos($xml, '<w:tr ', ((strlen($xml)-$offset)*-1));

		if (!$rowStart)
		{
			$rowStart = strrpos($xml, '<w:tr>', ((strlen($xml)-$offset)*-1));
		}

		if (!$rowStart)
		{
			throw new RuntimeException
			(
				"Can not find the start position of the row to clone."
			);
		}

		return $rowStart;
	}

	/**
	 * Method: findRowEnd
	 * =========================================================================
	 * Find the end position of the nearest table row after $offset
	 * Used by [Method: cloneRow](#)
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $xml: The xml string to work with. __STRING not SimpleXMLElement__
	 *  - $offset: The offset
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * int
	 */
	protected function findRowEnd($xml, $offset)
	{
		$rowEnd = strpos($xml, "</w:tr>", $offset) + 7;
		return $rowEnd;
	}

	/**
	 * Method: createTempDocument
	 * =========================================================================
	 * We don't want to make any changes to the actual template document.
	 * So we need to copy the template to a temp location and edit that copy.
	 * This makes the copy.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 *  - RuntimeException: If a path to the temp file could not be created.
	 */
	protected function createTempDocument()
	{
		if (($temp = tempnam(sys_get_temp_dir(), 'GearsPdf')) === false)
		{
			throw new RuntimeException
			(
				'Could not create temporary file with unique name '.
				'in your systems default temporary directory.'
			);
		}

		$this->tempDocument = $this->fileInfo($temp);

		$this->fileSystem->copy($this->template, $this->tempDocument, true);
	}

	/**
	 * Method: readTempDocument
	 * =========================================================================
	 * This method uses ```ZipArchive``` to open up the temp docx template file.
	 * It populates the [Property: documentXML](#), [Property: headerXMLs](#)
	 * & [Property: footerXMLs](#).
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	protected function readTempDocument()
	{
		$this->createTempDocument();

		if ($this->zip->open($this->tempDocument) !== true)
		{
			throw new RuntimeException
			(
				'Failed to open the temp template document!'
			);
		}

		// Read in the headers
		$index = 1;
		while ($this->zip->locateName($this->getHeaderName($index)) !== false)
		{
			$this->headerXMLs[$index] = $this->fixSplitTags
			(
				$this->xml($this->zip->getFromName
				(
					$this->getHeaderName($index)
				))
			);

			$index++;
		}

		// Read in the main body
		$this->documentXML = $this->fixSplitTags
		(
			$this->xml
			(
				$this->zip->getFromName('word/document.xml')
			)
		);

		// Read in the footers
		$index = 1;
		while ($this->zip->locateName($this->getFooterName($index)) !== false)
		{
			$this->footerXMLs[$index] = $this->fixSplitTags
			(
				$this->xml($this->zip->getFromName
				(
					$this->getFooterName($index)
				))
			);
			
			$index++;
		}
	}

	/**
	 * Method: writeTempDocument
	 * =========================================================================
	 * After we have done any searching replacing, we need to write the
	 * modified XML back into the temporary docx document.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	protected function writeTempDocument()
	{
		// Write the headers
		foreach ($this->headerXMLs as $index => $headerXML)
		{
			$this->zip->addFromString
			(
				$this->getHeaderName($index),
				$this->headerXMLs[$index]->asXml()
			);
		}

		// Write the main body
		$xml = $this->documentXML->asXml();
		$this->zip->addFromString('word/document.xml', $xml);

		// Write the footers
		foreach ($this->footerXMLs as $index => $headerXML)
		{
			$this->zip->addFromString
			(
				$this->getFooterName($index),
				$this->footerXMLs[$index]->asXml()
			);
		}

		// Close zip file
		if ($this->zip->close() === false)
		{
			throw new RuntimeException
			(
				'Could not close the temp template document!'
			);
		}
	}

	/**
	 * Method: deleteTempDocument
	 * =========================================================================
	 * Once we are finished with the temp document we need to delete it.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 * n/a
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	protected function deleteTempDocument()
	{
		$this->fileSystem->remove($this->tempDocument);
	}

	/**
	 * Method:getHeaderName
	 * =========================================================================
	 * Get the name of the header file for $index.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $index: The number of the header.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * string
	 */
	protected function getHeaderName($index)
	{
		return sprintf('word/header%d.xml', $index);
	}

	/**
	 * Method:getFooterName
	 * =========================================================================
	 * Get the name of the footer file for $index.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $index: The number of the footer.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * string
	 */
	protected function getFooterName($index)
	{
		return sprintf('word/footer%d.xml', $index);
	}

	/**
	 * Method: setValueForPart
	 * =========================================================================
	 * Find and replace placeholders in the given XML section.
	 * 
	 * > NOTE: This is not part of the public API.
	 * 
	 * Paramters:
	 * -------------------------------------------------------------------------
	 *  - $xml: The xml string that we are to act on.
	 *  - $search: The tag to search for. ie: ${MYTAG}
	 *  - $replace: A value to replace the tag with.
	 *  - $limit: How many times to do the replacement.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * ```SimpleXMLElement```
	 */
	protected function setValueForPart($xml, $search, $replace, $limit)
	{
		// Make sure the search value contains the tag syntax
		$search = $this->normaliseStartTag($search);

		// Make sure the replacement value is encoded correctly.
		$replace = htmlspecialchars(Str::toUTF8($replace));

		// Do the search and replace
		return $this->xml(preg_replace
		(
			'/'.preg_quote($search, '/').'/u',
			$replace,
			$xml->asXml(),
			$limit
		));
	}

	/**
	 * Method: normaliseStartTag
	 * =========================================================================
	 * This ensures that the start tag contains the correct syntax.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $value: The tag name, with or without curleys.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * string
	 */
	protected function normaliseStartTag($value)
	{
		if (substr($value, 0, 2) !== '${' && substr($value, -1) !== '}')
		{
			$value = '${'.$value.'}';
		}

		return $value;
	}

	/**
	 * Method: normaliseEndTag
	 * =========================================================================
	 * This ensures that the end tag contains the correct syntax.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $value: The tag name, with or without curleys.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * string
	 */
	protected function normaliseEndTag($value)
	{
		if (substr($value, 0, 2) !== '${/' && substr($value, -1) !== '}')
		{
			$value = '${/'.$value.'}';
		}

		return $value;
	}

	/**
	 * Method: getStartAndEndNodes
	 * =========================================================================
	 * Searches the xml with the given blockname and returns
	 * the corresponding start and end nodes.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $xml: An instance of ```SimpleXMLElement```
	 *  - $blockname: The name of the block to find.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * array
	 */
	protected function getStartAndEndNodes($xml, $blockname)
	{
		// Assume the nodes don't exist
		$startNode = false; $endNode = false;

		// Search for the block start and end tags
		foreach ($xml->xpath('//w:t') as $node)
		{
			if (Str::contains($node, $this->normaliseStartTag($blockname)))
			{
				$startNode = $node;
				continue;
			}

			if (Str::contains($node, $this->normaliseEndTag($blockname)))
			{
				$endNode = $node;
				break;
			}
		}

		// Bail out if we couldn't find anything
		if ($startNode === false || $endNode === false) return false;

		// Find the parent <w:p> node for the start tag
		$node = $startNode; $startNode = null;
		while (is_null($startNode))
		{
			$node = $node->xpath('..')[0];

			if ($node->getName() == 'p')
			{
				$startNode = $node;
			}
		}

		// Find the parent <w:p> node for the end tag
		$node = $endNode; $endNode = null;
		while (is_null($endNode))
		{
			$node = $node->xpath('..')[0];

			if ($node->getName() == 'p')
			{
				$endNode = $node;
			}
		}

		// Return the start and end node
		return [$startNode, $endNode];
	}

	/**
	 * Method: getBlockRegx
	 * =========================================================================
	 * Builds the regular expression to get the
	 * xml block between the start and end nodes.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $nodes: An array containing the start and end nodes.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * string
	 */
	protected function getBlockRegx($nodes)
	{
		$pattern = '/';
		$pattern .= preg_quote($nodes[0]->asXml(), '/');
		$pattern .= '(.*?)';
		$pattern .= preg_quote($nodes[1]->asXml(), '/');
		$pattern .= '/is';

		return $pattern;
	}

	/**
	 * Method: searchForBlock
	 * =========================================================================
	 * This will search for the xml block between the start and end tags.
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $xml: An instance of ```SimpleXMLElement```
	 *  - $blockname: The name of the block.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * array
	 */
	protected function searchForBlock($xml, $blockname)
	{
		// Find the starting and ending tags
		$nodes = $this->getStartAndEndNodes($this->documentXML, $blockname);

		// Bail out early
		if ($nodes === false) return null;

		// Find the xml in between the nodes
		preg_match($this->getBlockRegx($nodes), $xml->asXml(), $matches);

		return $matches;
	}

	/**
	 * Method: fixSplitTags
	 * =========================================================================
	 * If part of the tag is formatted differently we won't get a match.
	 * Best explained with an example:
	 * 
	 * ```xml
	 * <w:r>
	 * 	<w:rPr/>
	 * 	<w:t>Hello ${tag_</w:t>
	 * </w:r>
	 * <w:r>
	 * 	<w:rPr>
	 * 		<w:b/>
	 * 		<w:bCs/>
	 * 	</w:rPr>
	 * 	<w:t>1}</w:t>
	 * </w:r>
	 * ```
	 * 
	 * The above becomes, after running through this method:
	 * 
	 * ```xml
	 * <w:r>
	 * 	<w:rPr/>
	 * 	<w:t>Hello ${tag_1}</w:t>
	 * </w:r>
	 * ```
	 */
	protected function fixSplitTags($xml)
	{
		$xml = $xml->asXml();

		preg_match_all('|\$\{([^\}]+)\}|U', $xml, $matches);

		foreach ($matches[0] as $value)
		{
			$valueCleaned = preg_replace('/<[^>]+>/', '', $value);
			$valueCleaned = preg_replace('/<\/[^>]+>/', '', $valueCleaned);
			$xml = str_replace($value, $valueCleaned, $xml);
		}

		return $this->xml($xml);
	}
}