The Pdf Gear
================================================================================
[![Build Status](https://travis-ci.org/phpgearbox/pdf.svg)](https://travis-ci.org/phpgearbox/pdf)
[![Latest Stable Version](https://poser.pugx.org/gears/pdf/v/stable.svg)](https://packagist.org/packages/gears/pdf)
[![Total Downloads](https://poser.pugx.org/gears/pdf/downloads.svg)](https://packagist.org/packages/gears/pdf)
[![License](https://poser.pugx.org/gears/pdf/license.svg)](https://packagist.org/packages/gears/pdf)

This project started life as a DOCX templating engine. It has now envolved to
also support converting HTML to PDF using a headless version of _webkit_,
[phantomjs](http://phantomjs.org/).

The DOCX templating is great for documents that end clients update and manage
over time, particularly text heavy documents. For example I use it to auto
generate some legal contracts, where simple replacements are made for attributes
like First Name, Last Name, Company Name & Address. The client, an insurance
company, can provide updated template word documents that might contain subtle
changes to policies & other conditions.

The HTML to PDF engine is great for cases where greater control over the design
of the document is required. It's also more natural for us programmers, using
standard HTML & CSS, with a splash of Javscript.

How to Install
--------------------------------------------------------------------------------
Installation via composer is easy:

	composer require gears/pdf:*

You will also need to add the following to your root ```composer.json``` file.

	"scripts":
	{
		"post-install-cmd": ["PhantomInstaller\\Installer::installPhantomJS"],
		"post-update-cmd": ["PhantomInstaller\\Installer::installPhantomJS"]
	}

> DOCX: If you are going to be using the DOCX templating you will need to
> install either libre-office-headless or unoconv on your host.

How to Use, the basics
--------------------------------------------------------------------------------
Both APIs are accessed through the main ```Pdf``` class.

To convert a word document into a PDF without any templating:
```php
$pdf = Gears\Pdf::convert('/path/to/document.docx');
```

To save the generated PDF to a file:
```php
Gears\Pdf::convert('/path/to/document.docx', '/path/to/document.pdf');
```

To convert a html document into a PDF:
```php
$pdf = Gears\Pdf::convert('/path/to/document.html');
```

> NOTE: The save to file works just the same for a HTML document.

DOCX Templating
--------------------------------------------------------------------------------
By default the DOCX backend defaults to using ```libre-office-headless```,
to use ```unoconv```, override the converter like so:
```php
$document = new Gears\Pdf('/path/to/document.docx');
$document->converter = function()
{
	return new Gears\Pdf\Docx\Converter\Unoconv();
};
$document->save('/path/to/document.pdf');
```

> NOTE: Currently the HTML backend only uses phantomjs.

There are several templating methods for the DOCX engine.
The first is setValue, this replaces all instances of
```${FOO}``` with ```BAR```
```php
$document->setValue('FOO', 'BAR');
```

To clone an entire block of DOCX xml, you surround your block with tags like:
```${BLOCK_TO_CLONE}``` & ```${/BLOCK_TO_CLONE}```. Whatever content is
contained inside this block will be repeated 3 times in the generated PDF.
```php
$document->cloneBlock('BLOCK_TO_CLONE', 3);
```

If you need to replace an entire block with custom DOCX xml you can.
But you need to make sure your XML conforms to the DOCX standards.
This is a very low level method and I wouldn't normally use this.
```php
$document->replaceBlock('BLOCK_TO_REPLACE', '<docx><xml></xml></docx>');
```

To delete an entire block, for example you might have particular
sections of the document that you only want to show to certian users.
```php
$document->deleteBlock('BLOCK_TO_DELETE');
```

Finally the last method is useful for adding new rows to tables.
Similar to the ```cloneBlock``` method. You place the tag in first cell
of the table. This row is the one that gets cloned.
```php
$document->cloneRow('ROW_TO_CLONE', 5);
```

__For more examples please see the [Unit Tests](https://github.com/phpgearbox/pdf/tree/master/tests).
These contain the PHP code to generate the final PDF along with the original DOCX templates.__

> NOTE: The HTML to PDF converter does not have these same templating functions.
> Obviously it's just standard HTML that you can template how ever you like.

HTML PhantomJs Print Environment
--------------------------------------------------------------------------------
This is still in development and subject to radical change.
So I won't document this section just yet...

Credits
--------------------------------------------------------------------------------
The DOCX templating code originally came from
[PHPWord](https://github.com/PHPOffice/PHPWord)

You may still like to use _PHPWord_ to generate your DOCX documents.
And then use this package to convert the generated document to PDF.

--------------------------------------------------------------------------------
Developed by Brad Jones - brad@bjc.id.au