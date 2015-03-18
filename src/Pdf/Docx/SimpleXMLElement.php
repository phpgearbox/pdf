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

use SimpleXMLElement as NativeSimpleXMLElement;

class SimpleXMLElement extends NativeSimpleXMLElement
{
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
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $xml: A well-formed XML string.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * string
	 */
	public static function fixSplitTags($xml)
	{
		preg_match_all('|\$\{([^\}]+)\}|U', $xml, $matches);

		foreach ($matches[0] as $value)
		{
			$valueCleaned = preg_replace('/<[^>]+>/', '', $value);
			$valueCleaned = preg_replace('/<\/[^>]+>/', '', $valueCleaned);
			$xml = str_replace($value, $valueCleaned, $xml);
		}

		return new static($xml);
	}
}