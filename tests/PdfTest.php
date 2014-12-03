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

class PdfTest extends PHPUnit_Framework_TestCase
{
	protected $pdfBox;

	protected function setUp()
	{
		$this->pdfBox = new PdfBox;
		$this->pdfBox->setPathToPdfBox('./tests/pdfbox-app-1.8.7.jar');
	}

	public function testConvert()
	{
		Gears\Pdf::convert('./tests/templates/Convert.docx', './tests/output/Convert.pdf');

		$text1 = file_get_contents('./tests/expected/Convert.txt');
		$text2 = $this->pdfBox->textFromPdfFile('./tests/output/Convert.pdf');

		$text1 = str_replace(["\n", ' '], '', $text1);
		$text2 = str_replace(["\n", ' '], '', $text2);

		$this->assertEquals($text1, $text2);
	}

	/*public function testSetValue()
	{
		$document = new Gears\Pdf('./tests/templates/SetValue.docx');
		$document->setValue('name', 'Brad Jones');
		$document->save('./tests/output/SetValue.pdf');

		$text = $this->pdfBox->textFromPdfFile('./tests/output/SetValue.pdf');

		$this->assertEquals("Hello Brad Jones.\n", $text);
	}

	public function testCloneBlock()
	{
		$document = new Gears\Pdf('./tests/templates/CloneBlock.docx');
		$document->cloneBlock('CLONEME', 3);
		$document->save('./tests/output/CloneBlock.pdf');

		$text = $this->pdfBox->textFromPdfFile('./tests/output/CloneBlock.pdf');

		$this->assertFalse(Str::contains($text, '${CLONEME}'));
		$this->assertEquals(3, substr_count($text, 'PHPWord can apply font'));
		$this->assertFalse(Str::contains($text, '${/CLONEME}'));
	}

	public function testReplaceBlock()
	{
		$document = new Gears\Pdf('./tests/templates/ReplaceBlock.docx');
		$document->replaceBlock('REPLACEME', '<w:p><w:pPr><w:pStyle w:val="PreformattedText"/><w:rPr/></w:pPr><w:r><w:rPr/><w:t>I am replaced.</w:t></w:r></w:p>');
		$document->save('./tests/output/ReplaceBlock.pdf');

		$text = $this->pdfBox->textFromPdfFile('./tests/output/ReplaceBlock.pdf');

		$this->assertFalse(Str::contains($text, '${REPLACEME}'));
		$this->assertTrue(Str::contains($text, 'I am replaced.'));
		$this->assertFalse(Str::contains($text, '${/REPLACEME}'));
	}

	public function testDeleteBlock()
	{
		$document = new Gears\Pdf('./tests/templates/DeleteBlock.docx');
		$document->deleteBlock('DELETEME');
		$document->save('./tests/output/DeleteBlock.pdf');

		$text = $this->pdfBox->textFromPdfFile('./tests/output/DeleteBlock.pdf');

		$this->assertFalse(Str::contains($text, '${DELETEME}'));
		$this->assertFalse(Str::contains($text, 'This should be deleted.'));
		$this->assertFalse(Str::contains($text, '${/DELETEME}'));
	}

	public function testCloneRow()
	{
		$document = new Gears\Pdf('./tests/templates/CloneRow.docx');

		$document->cloneRow('rowValue', 10);
		$document->setValue('rowValue_1', 'Sun');
		$document->setValue('rowValue_2', 'Mercury');
		$document->setValue('rowValue_3', 'Venus');
		$document->setValue('rowValue_4', 'Earth');
		$document->setValue('rowValue_5', 'Mars');
		$document->setValue('rowValue_6', 'Jupiter');
		$document->setValue('rowValue_7', 'Saturn');
		$document->setValue('rowValue_8', 'Uranus');
		$document->setValue('rowValue_9', 'Neptun');
		$document->setValue('rowValue_10', 'Pluto');
		$document->setValue('rowNumber_1', '1');
		$document->setValue('rowNumber_2', '2');
		$document->setValue('rowNumber_3', '3');
		$document->setValue('rowNumber_4', '4');
		$document->setValue('rowNumber_5', '5');
		$document->setValue('rowNumber_6', '6');
		$document->setValue('rowNumber_7', '7');
		$document->setValue('rowNumber_8', '8');
		$document->setValue('rowNumber_9', '9');
		$document->setValue('rowNumber_10', '10');

		$document->cloneRow('userId', 3);
		$document->setValue('userId_1', '1');
		$document->setValue('userFirstName_1', 'James');
		$document->setValue('userName_1', 'Taylor');
		$document->setValue('userPhone_1', '+1 428 889 773');
		$document->setValue('userId_2', '2');
		$document->setValue('userFirstName_2', 'Robert');
		$document->setValue('userName_2', 'Bell');
		$document->setValue('userPhone_2', '+1 428 889 774');
		$document->setValue('userId_3', '3');
		$document->setValue('userFirstName_3', 'Michael');
		$document->setValue('userName_3', 'Ray');
		$document->setValue('userPhone_3', '+1 428 889 775');

		$document->save('./tests/output/CloneRow.pdf');

		$html = $this->pdfBox->htmlFromPdfFile('./tests/output/CloneRow.pdf');
		$html = str_replace("\n", ' ', $html);
		$html = preg_replace('~>\s+<~', '><', $html);

		$this->assertTrue(Str::contains($html, 'Value 1: <b>Sun </b>'));
		$this->assertTrue(Str::contains($html, 'Value 2: <b>Mercury </b>'));
		$this->assertTrue(Str::contains($html, 'Value 3: <b>Venus </b>'));
		$this->assertTrue(Str::contains($html, 'Value 4: <b>Earth </b>'));
		$this->assertTrue(Str::contains($html, 'Value 5: <b>Mars </b>'));
		$this->assertTrue(Str::contains($html, 'Value 6: <b>Jupiter </b>'));
		$this->assertTrue(Str::contains($html, 'Value 7: <b>Saturn </b>'));
		$this->assertTrue(Str::contains($html, 'Value 8: <b>Uranus </b>'));
		$this->assertTrue(Str::contains($html, 'Value 9: <b>Neptun </b>'));
		$this->assertTrue(Str::contains($html, 'Value 10: <b>Pluto </b>'));

		$this->assertTrue(Str::contains($html, '<p>1 Name Taylor First name James Phone +1 428 889 773 </p>'));
		$this->assertTrue(Str::contains($html, '<p>2 Name Bell First name Robert Phone +1 428 889 774 </p>'));
		$this->assertTrue(Str::contains($html, '<p>3 Name Ray First name Michael Phone +1 428 889 775 </p>'));
	}*/
}