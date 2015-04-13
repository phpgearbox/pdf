<?php
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

use Gears\String as Str;
use SGH\PdfBox\PdfBox;

class PdfPhantomJsTest extends PHPUnit_Framework_TestCase
{
	protected $pdfBox;
	
	protected function setUp()
	{
		$this->pdfBox = new PdfBox;
		$this->pdfBox->setPathToPdfBox('./tests/pdfbox-app-1.8.7.jar');
	}
	
	public function testConvert()
	{
		$html = file_get_contents('./tests/templates/PhantomJs.html');
		
		$result = Gears\Pdf::convert($html, './tests/output/PhantomJsConvert.pdf');
		
		$this->assertInstanceOf('SplFileInfo', $result);
		
		$this->assertFileExists('./tests/output/PhantomJsConvert.pdf');
		
		$text = Str::s($this->pdfBox->textFromPdfFile('./tests/output/PhantomJsConvert.pdf'))->to('ascii');
		
		$this->assertTrue($text->contains('Iamthecoverpage.'));
		
		$this->assertTrue($text->contains('B15/15'));
	}
}
