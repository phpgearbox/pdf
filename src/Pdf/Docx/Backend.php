<?php namespace Gears\Pdf\Docx;
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

use Gears\String\Str;
use ZipArchive;
use RuntimeException;
use Gears\Di\Container;
use Gears\Pdf\TempFile;
use Gears\Pdf\Docx\SimpleXMLElement;
use Gears\Pdf\Docx\Converter\LibreOffice;
use Gears\Pdf\Contracts\Backend as BackendInterface;

class Backend extends Container implements BackendInterface
{
	/**
	 * @var Gears\Pdf\TempFile DOCX document to use as the template for our PDF.
	 *                         Set as the first argument of the constructor of
	 *                         this class.
	 */
	protected $template;

	/**
	 * This is where store the main ```word/document.xml``` of the docx file.
	 * It will be an instance of ```SimpleXMLElement```.
	 */
	protected $documentXML;

	/**
	 * This is where store any header xml. ie: ```word/header1.xml```.
	 * It will contains instances of ```SimpleXMLElement```.
	 */
	protected $headerXMLs = [];

	/**
	 * This is where store any footer xml. ie: ```word/footer1.xml```.
	 * It will contains instances of ```SimpleXMLElement```.
	 */
	protected $footerXMLs = [];

	/**
	 * An instance of ```ZipArchive```.
	 */
	protected $injectZip;

	/**
	 * A closure that returns an instance of ```SimpleXMLElement```
	 */
	protected $injectXml;

	/**
	 * This must be supplied before any converstions will take place.
	 */
	protected $injectConverter;

	/**
	 * Set Container Defaults
	 * 
	 * This is where we set all our defaults. If you need to customise this
	 * container this is a good place to look to see what can be configured
	 * and how to configure it.
	 */
	protected function setDefaults()
	{
		$this->zip = function()
		{
			return new ZipArchive;
		};

		$this->xml = $this->protect(function($xml)
		{
			return SimpleXMLElement::fixSplitTags($xml);
		});

		$this->converter = function()
		{
			return new LibreOffice();
		};
	}

	/**
	 * Configures this container.
	 * 
	 * @param TempFile $document The docx file we will convert.
	 * 
	 * @param array $config Further configuration for this container.
	 */
	public function __construct(TempFile $document, $config = [])
	{
		parent::__construct($config);

		$this->template = $document;
		
		$this->readDocx();
	}
	
	/**
	 * Generates the PDF from the DOCX File
	 * 
	 * @return PDF Bytes
	 */
	public function generate()
	{
		$this->writeDocx();
		
		return $this->converter->convertDoc($this->template);
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
	protected function readDocx()
	{
		// Open the document
		if ($this->zip->open($this->template) !== true)
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
			$this->headerXMLs[$index] = $this->xml
			(
				$this->zip->getFromName
				(
					$this->getHeaderName($index)
				)
			);

			$index++;
		}

		// Read in the main body
		$this->documentXML = $this->xml
		(
			$this->zip->getFromName('word/document.xml')
		);

		// Read in the footers
		$index = 1;
		while ($this->zip->locateName($this->getFooterName($index)) !== false)
		{
			$this->footerXMLs[$index] = $this->xml
			(
				$this->zip->getFromName
				(
					$this->getFooterName($index)
				)
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
	protected function writeDocx()
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
//		$replace = htmlspecialchars(Str::toUTF8($replace));

        $replace = htmlspecialchars($replace);
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
}
