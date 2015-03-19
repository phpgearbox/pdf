<?php namespace Gears\Pdf;
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
use SplFileInfo as NativeSplFileInfo;

class TempFile extends NativeSplFileInfo
{
	/**
	 * We overload the parent constructor to auto generate a new temp filename.
	 *
	 * @param string $prefix The prefix of the generated temporary filename.
	 * @param string $ext The extension to give the file.
	 */
	public function __construct($prefix = null, $ext = null)
	{
		if (($temp = tempnam(sys_get_temp_dir(), $prefix)) === false)
		{
			throw new RuntimeException
			(
				'Could not create temporary file with unique name '.
				'in your systems default temporary directory.'
			);
		}

		if (!empty($ext))
		{
			rename($temp, $temp.'.'.$ext);
			return parent::__construct($temp.'.'.$ext);
		}

		return parent::__construct($temp);
	}

	/**
	 * Deletes the underlying file when this class is garbage collected.
	 */
	public function __destruct()
	{
		if (is_file($this->getPathname()))
		{
			unlink($this->getPathname());
		}
	}

	/**
	 * Returns the contents of the file.
	 *
	 * Thanks Fabien :)
	 *
	 * @return string the contents of the file
	 *
	 * @throws RuntimeException
	 */
	public function getContents()
	{
		$level = error_reporting(0);
		$content = file_get_contents($this->getPathname());
		error_reporting($level);

		if (false === $content)
		{
			$error = error_get_last();
			throw new RuntimeException($error['message']);
		}

		return $content;
	}

	/**
	 * Sets the contents of the temp file.
	 *
	 * @param mixed $data Can be either a string, an array or a stream resource.
	 *
	 * @return mixed This function returns the number of bytes that were
	 *               written to the file;
	 *
	 * @throws RuntimeException
	 */
	public function setContents($data)
	{
		$level = error_reporting(0);
		$result = file_put_contents($this->getPathname(), $data);
		error_reporting($level);

		if (false === $result)
		{
			$error = error_get_last();
			throw new RuntimeException($error['message']);
		}

		return $result;
	}
}
