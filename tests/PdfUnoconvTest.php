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

class PdfUnoconvTest extends PHPUnit_Framework_TestCase
{
	protected $pdfBox;

	protected function setUp()
	{
		$this->pdfBox = new PdfBox;
		$this->pdfBox->setPathToPdfBox('./tests/pdfbox-app-1.8.7.jar');

		$this->converter = function()
		{
			return new Gears\Pdf\Docx\Converter\Unoconv();
		};
	}

	public function testConvert()
	{
		Gears\Pdf::convert('./tests/templates/Convert.docx', './tests/output/UnoconvConvert.pdf', ['converter' => $this->converter]);

		$text = Str::s($this->pdfBox->textFromPdfFile('./tests/output/UnoconvConvert.pdf'))->to('ascii');

		$this->assertTrue($text->contains('Demonstration of DOCX support'));
	}

	public function testSetValue()
	{
		$document = new Gears\Pdf('./tests/templates/SetValue.docx');
		$document->converter = $this->converter;
		$document->setValue('name', 'Brad Jones');
		$document->save('./tests/output/UnoconvoSetValue.pdf');

		$text = Str::s($this->pdfBox->textFromPdfFile('./tests/output/UnoconvoSetValue.pdf'))->to('ascii');

		$this->assertTrue($text->contains('Hello Brad Jones.'));
	}

	public function testCloneBlock()
	{
		$document = new Gears\Pdf('./tests/templates/CloneBlock.docx');
		$document->converter = $this->converter;
		$document->cloneBlock('CLONEME', 3);
		$document->save('./tests/output/UnoconvoCloneBlock.pdf');

		$text = Str::s($this->pdfBox->textFromPdfFile('./tests/output/UnoconvoCloneBlock.pdf'))->to('ascii');

		$this->assertFalse($text->contains('${CLONEME}'));
		$this->assertEquals(3, substr_count($text, 'PHPWord can apply font'));
		$this->assertFalse($text->contains('${/CLONEME}'));
	}

	public function testReplaceBlock()
	{
		$document = new Gears\Pdf('./tests/templates/ReplaceBlock.docx');
		$document->converter = $this->converter;
		$document->replaceBlock('REPLACEME', '<w:p><w:pPr><w:pStyle w:val="PreformattedText"/><w:rPr/></w:pPr><w:r><w:rPr/><w:t>I am replaced.</w:t></w:r></w:p>');
		$document->save('./tests/output/UnoconvoReplaceBlock.pdf');

		$text = Str::s($this->pdfBox->textFromPdfFile('./tests/output/UnoconvoReplaceBlock.pdf'))->to('ascii');

		$this->assertFalse($text->contains('${REPLACEME}'));
		$this->assertTrue($text->contains('I am replaced.'));
		$this->assertFalse($text->contains('${/REPLACEME}'));
	}

	public function testDeleteBlock()
	{
		$document = new Gears\Pdf('./tests/templates/DeleteBlock.docx');
		$document->converter = $this->converter;
		$document->deleteBlock('DELETEME');
		$document->save('./tests/output/UnoconvoDeleteBlock.pdf');

		$text = Str::s($this->pdfBox->textFromPdfFile('./tests/output/UnoconvoDeleteBlock.pdf'))->to('ascii');

		$this->assertFalse($text->contains('${DELETEME}'));
		$this->assertFalse($text->contains('This should be deleted.'));
		$this->assertFalse($text->contains('${/DELETEME}'));
	}

	public function testCloneRow()
	{
		$document = new Gears\Pdf('./tests/templates/CloneRow.docx');
		$document->converter = $this->converter;

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

		$document->save('./tests/output/UnoconvoCloneRow.pdf');

		$text = Str::s($this->pdfBox->textFromPdfFile('./tests/output/UnoconvoCloneRow.pdf'))->to('ascii');

		$this->assertTrue($text->contains('Value 1: Sun'));
		$this->assertTrue($text->contains('Value 2: Mercury'));
		$this->assertTrue($text->contains('Value 3: Venus'));
		$this->assertTrue($text->contains('Value 4: Earth'));
		$this->assertTrue($text->contains('Value 5: Mars'));
		$this->assertTrue($text->contains('Value 6: Jupiter'));
		$this->assertTrue($text->contains('Value 7: Saturn'));
		$this->assertTrue($text->contains('Value 8: Uranus'));
		$this->assertTrue($text->contains('Value 9: Neptun'));
		$this->assertTrue($text->contains('Value 10: Pluto'));

		$this->assertTrue($text->contains("1 Name TaylorFirst name JamesPhone +1 428 889 773"));
		$this->assertTrue($text->contains("2 Name BellFirst name RobertPhone +1 428 889 774"));
		$this->assertTrue($text->contains("3 Name RayFirst name MichaelPhone +1 428 889 775"));
	}
}
