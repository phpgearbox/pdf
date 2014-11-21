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
use RuntimeException;
use Gears\Di\Container;
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
	 */
	protected $documentXML;

	/**
	 * Property: headerXMLs
	 * =========================================================================
	 * This is where store any header xml. ie: ```word/header1.xml```.
	 */
	protected $headerXMLs = [];

	/**
	 * Property: footerXMLs
	 * =========================================================================
	 * This is where store any footer xml. ie: ```word/footer1.xml```.
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
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $path: Optionally you may supply an output path of the pdf,
	 *    if not supplied we will create the PDF in the name folder as
	 *    the source document with the same filename.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 * 
	 * Throws:
	 * -------------------------------------------------------------------------
	 *  - RuntimeException: If a path to the temp file could not be created.
	 */
	public function save($path = null)
	{
		if (!$this->tempDocument)
		{
			$doc = $this->tempDocument;
			$this->writeTempDocument();
		}
		else
		{
			// If some one is just doing a simple conversion
			// we can just use the original template document.
			$doc = $this->template;
		}

		// Build the unoconv cmd
		$cmd = $this->unoconvBin.' -f pdf';
		if (!is_null($path)) $cmd .= ' -o '.$path;
		$cmd .= $doc->getPathname();

		// This may help with sudo type issues:
		// http://stackoverflow.com/questions/8532304/execute-root-commands-via-php

		// Run the command
		system($cmd);
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
	 * Set a Template value
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $search:
	 *  - $replace:
	 *  - $limit:
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function setValue($search, $replace, $limit = -1)
	{
		if (!$this->tempDocument) $this->readTempDocument();

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
     * Clone a table row in a template document
     *
     * @param string $search
     * @param integer $numberOfClones
     * @throws \PhpOffice\PhpWord\Exception\Exception
     */
    public function cloneRow($search, $numberOfClones)
    {
    	if (!$this->tempDocument) $this->readTempDocument();

        if (substr($search, 0, 2) !== '${' && substr($search, -1) !== '}') {
            $search = '${' . $search . '}';
        }

        $tagPos = strpos($this->documentXML, $search);
        if (!$tagPos) {
            throw new Exception("Can not clone row, template variable not found or variable contains markup.");
        }

        $rowStart = $this->findRowStart($tagPos);
        $rowEnd = $this->findRowEnd($tagPos);
        $xmlRow = $this->getSlice($rowStart, $rowEnd);

        // Check if there's a cell spanning multiple rows.
        if (preg_match('#<w:vMerge w:val="restart"/>#', $xmlRow)) {
            // $extraRowStart = $rowEnd;
            $extraRowEnd = $rowEnd;
            while (true) {
                $extraRowStart = $this->findRowStart($extraRowEnd + 1);
                $extraRowEnd = $this->findRowEnd($extraRowEnd + 1);

                // If extraRowEnd is lower then 7, there was no next row found.
                if ($extraRowEnd < 7) {
                    break;
                }

                // If tmpXmlRow doesn't contain continue, this row is no longer part of the spanned row.
                $tmpXmlRow = $this->getSlice($extraRowStart, $extraRowEnd);
                if (!preg_match('#<w:vMerge/>#', $tmpXmlRow) &&
                    !preg_match('#<w:vMerge w:val="continue" />#', $tmpXmlRow)) {
                    break;
                }
                // This row was a spanned row, update $rowEnd and search for the next row.
                $rowEnd = $extraRowEnd;
            }
            $xmlRow = $this->getSlice($rowStart, $rowEnd);
        }

        $result = $this->getSlice(0, $rowStart);
        for ($i = 1; $i <= $numberOfClones; $i++) {
            $result .= preg_replace('/\$\{(.*?)\}/', '\${\\1#' . $i . '}', $xmlRow);
        }
        $result .= $this->getSlice($rowEnd);

        $this->documentXML = $result;
    }

    /**
     * Clone a block
     *
     * @param string $blockname
     * @param integer $clones
     * @param boolean $replace
     * @return string|null
     */
    public function cloneBlock($blockname, $clones = 1, $replace = true)
    {
    	if (!$this->tempDocument) $this->readTempDocument();

    	// Parse the XML
    	$xml = new \SimpleXMLElement($this->documentXML);

    	// Find the starting and ending tags
    	$startNode = false; $endNode = false;
    	foreach ($xml->xpath('//w:t') as $node)
    	{
    		if (strpos($node, '${'.$blockname.'}') !== false)
    		{
    			$startNode = $node;
    			continue;
    		}

    		if (strpos($node, '${/'.$blockname.'}') !== false)
    		{
    			$endNode = $node;
    			break;
    		}
    	}

    	// Make sure we found the tags
    	if ($startNode === false || $endNode === false)
    	{
    		return null;
    	}

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

    	/*
    	 * NOTE: Because SimpleXML reduces empty tags to "self-closing" tags.
    	 * We need to replace the original XML with the version of XML as
    	 * SimpleXML sees it. The following example should show the issue
    	 * we are facing.
    	 * 
    	 * This is the XML that my document contained orginally.
    	 * 
    	 * ```xml
		 *	<w:p>
		 *		<w:pPr>
		 *			<w:pStyle w:val="TextBody"/>
		 *			<w:rPr></w:rPr>
		 *		</w:pPr>
		 *		<w:r>
		 *			<w:rPr></w:rPr>
		 *			<w:t>${CLONEME}</w:t>
		 *		</w:r>
		 *	</w:p>
		 * ```
		 * 
		 * This is the XML that SimpleXML returns from asXml().
		 * 
		 * ```xml
		 *  <w:p>
		 *		<w:pPr>
		 *			<w:pStyle w:val="TextBody"/>
		 *			<w:rPr/>
		 *		</w:pPr>
		 *		<w:r>
		 *			<w:rPr/>
		 *			<w:t>${CLONEME}</w:t>
		 *		</w:r>
		 *	</w:p>
		 * ```
    	 */

    	$this->documentXML = $xml->asXml();

    	// Find the xml in between the tags
    	$xmlBlock = null;
    	preg_match
    	(
            '/'.preg_quote($startNode->asXml(), '/').'(.*?)'.preg_quote($endNode->asXml(), '/').'/is',
            $this->documentXML,
            $matches
        );

        if (isset($matches[1]))
        {
			$xmlBlock = $matches[1];

			$cloned = array();

			for ($i = 1; $i <= $clones; $i++)
			{
				$cloned[] = preg_replace('/\${(.*?)}/','${$1_'.$i.'}', $xmlBlock);
			}

			if ($replace)
			{
				$this->documentXML = str_replace
				(
					$matches[0],
					implode('', $cloned),
					$this->documentXML
				);
			}
        }

        return $xmlBlock;
    }

    /**
     * Replace a block
     *
     * @param string $blockname
     * @param string $replacement
     */
    public function replaceBlock($blockname, $replacement)
    {
    	if (!$this->tempDocument) $this->readTempDocument();

        preg_match(
            '/(<\?xml.*)(<w:p.*>\${' . $blockname . '}<\/w:.*?p>)(.*)(<w:p.*\${\/' . $blockname . '}<\/w:.*?p>)/is',
            $this->documentXML,
            $matches
        );

        if (isset($matches[3])) {
            $this->documentXML = str_replace(
                $matches[2] . $matches[3] . $matches[4],
                $replacement,
                $this->documentXML
            );
        }
    }

    /**
     * Delete a block of text
     *
     * @param string $blockname
     */
    public function deleteBlock($blockname)
    {
        $this->replaceBlock($blockname, '');
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

		$this->tempDocument = new SplFileInfo($temp);

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

		$index = 1;
		while ($this->zip->locateName($this->getHeaderName($index)) !== false)
		{
			$this->headerXMLs[$index] = $this->zip->getFromName
			(
				$this->getHeaderName($index)
			);

			$index++;
		}

		$this->documentXML = $this->zip->getFromName('word/document.xml');

		$index = 1;
		while ($this->zip->locateName($this->getFooterName($index)) !== false)
		{
			$this->footerXMLs[$index] = $this->zip->getFromName
			(
				$this->getFooterName($index)
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
		foreach ($this->headerXMLs as $index => $headerXML)
		{
			$this->zip->addFromString
			(
				$this->getHeaderName($index),
				$this->headerXMLs[$index]
			);
		}

		$this->zip->addFromString('word/document.xml', $this->documentXML);

		foreach ($this->footerXMLs as $index => $headerXML)
		{
			$this->zip->addFromString
			(
				$this->getFooterName($index),
				$this->footerXMLs[$index]
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
	* Find and replace placeholders in the given XML section.
	*
	* @param string $documentPartXML
	* @param string $search
	* @param string $replace
	* @param integer $limit
	* @return string
	*/
	protected function setValueForPart($documentPartXML, $search, $replace, $limit)
	{
		$pattern = '|\$\{([^\}]+)\}|U';
		preg_match_all($pattern, $documentPartXML, $matches);
		foreach ($matches[0] as $value) {
		$valueCleaned = preg_replace('/<[^>]+>/', '', $value);
		$valueCleaned = preg_replace('/<\/[^>]+>/', '', $valueCleaned);
		$documentPartXML = str_replace($value, $valueCleaned, $documentPartXML);
		}

		if (substr($search, 0, 2) !== '${' && substr($search, -1) !== '}') {
		$search = '${' . $search . '}';
		}

		if (!String::isUTF8($replace)) {
		$replace = utf8_encode($replace);
		}
		$replace = htmlspecialchars($replace);

		$regExpDelim = '/';
		$escapedSearch = preg_quote($search, $regExpDelim);
		return preg_replace("{$regExpDelim}{$escapedSearch}{$regExpDelim}u", $replace, $documentPartXML, $limit);
	}
}