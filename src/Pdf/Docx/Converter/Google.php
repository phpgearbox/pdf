<?php namespace Gears\Pdf\Docx\Converter;
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
use Gears\Pdf\TempFile;
use Google_Client as GClient;
use Google_Http_Request as GRequest;
use Google_Auth_AssertionCredentials as GAuth;
use Google_Service_Drive as GDrive;
use Google_Service_Drive_DriveFile as GFile;
use Gears\Pdf\Contracts\DocxConverter;

class Google extends Container implements DocxConverter
{
	/**
	 * Property: serviceAccountEmail
	 * =========================================================================
	 * This is the email address shown at https://console.developers.google.com/
	 * For example it might look like:
	 * 
	 *     0123456789-abcdefghijklmnopqrstuvxyz@developer.gserviceaccount.com 
	 */
	protected $injectServiceAccountEmail;

	/**
	 * Property: serviceAccountKeyFile
	 * =========================================================================
	 * When you create a new Service Account at 
	 * https://console.developers.google.com/
	 * 
	 * You will get a "P12" private key. This is the location of that file.
	 */
	protected $injectServiceAccountKeyFile;

	/**
	 * Property: serviceAccountKey
	 * =========================================================================
	 * This holds the content of the key file.
	 */
	protected $injectServiceAccountKey;

	/**
	 * Property: scope
	 * =========================================================================
	 * A scope... just a url that points to google drive I dunno :)
	 */
	protected $injectScope;

	/**
	 * Property: mime
	 * =========================================================================
	 * For now we only support docx files.
	 */
	protected $injectMime;

	/**
	 * Property: auth
	 * =========================================================================
	 * This provides ```Google_Auth_AssertionCredentials```
	 */
	protected $injectAuth;

	/**
	 * Property: client
	 * =========================================================================
	 * This provides ```Google_Client```
	 */
	protected $injectClient;

	/**
	 * Property: service
	 * =========================================================================
	 * This provides ```Google_Service_Drive```
	 */
	protected $injectService;

	/**
	 * Property: file
	 * =========================================================================
	 * This provides ```Google_Service_Drive_DriveFile```
	 */
	protected $injectFile;

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
		$this->scope = ['https://www.googleapis.com/auth/drive'];

		$this->mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

		$this->serviceAccountKey = function()
		{
			return file_get_contents($this->serviceAccountKeyFile);
		};

		$this->auth = function()
		{
			return new GAuth
			(
				$this->serviceAccountEmail,
				$this->scope,
				$this->serviceAccountKey
			);
		};

		$this->client = function()
		{
			$c = new GClient();
			$c->setAssertionCredentials($this->auth);
			return $c;
		};

		$this->service = function()
		{
			return new GDrive($this->client);
		};

		$this->file = function()
		{
			$f = new GFile();
			$f->setTitle(time().'.docx');
			$f->setMimeType($this->mime);
			return $f;
		};

		$this->request = $this->protect(function($url)
		{
			return new GRequest($url, 'GET', null, null);
		});
	}

	/**
	 * Method: convertDoc
	 * =========================================================================
	 * This is where we actually do some converting of docx to pdf.
	 * We use the command line utility unoconv. Which is basically a slightly
	 * fancier way of using OpenOffice/LibreOffice Headless.
	 * 
	 * See: http://dag.wiee.rs/home-made/unoconv/
	 * 
	 * Parameters:
	 * -------------------------------------------------------------------------
	 *  - $docx: This must be an instance of ```SplFileInfo```
	 *           pointing to the document to convert.
	 * 
	 * Returns:
	 * -------------------------------------------------------------------------
	 * void
	 */
	public function convertDoc(TempFile $docx)
	{
		// Upload the document to google
		$gdoc = $this->service->files->insert($this->file,
		[
			'convert' => true,
			'data' => $docx->getContents(),
			'mimeType' => $this->mime,
			'uploadType' => 'multipart'
		]);

		// Now download the pdf
		$request = $this->request($gdoc->getExportLinks()['application/pdf']);
		$this->client->getAuth()->sign($request);
		$response = $this->client->getIo()->makeRequest($request);

		// Delete the uploaded file
		$this->service->files->delete($gdoc['id']);

		// Check for errors
		if ($response->getResponseHttpCode() != 200)
		{
			throw new RuntimeException($response->getResponseBody());
		}

		// Return the pdf data
		return $response->getResponseBody();
	}
}
