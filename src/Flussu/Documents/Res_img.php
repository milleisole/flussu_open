<?php
/* --------------------------------------------------------------------*
 * Flussu v4.2 - Mille Isole SRL - Released under Apache License 2.0
 * --------------------------------------------------------------------*
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * --------------------------------------------------------------------*
 
 Questa classe, derivata da un altra di David Newton (vedi
 info dopo questo manifesto) serve a manipolare una foto prima
 che venga gestita da Flussu.
 Ad esempio se scattata con la macchina fotografica ruotata,
 viene correttamente ruotata e migliorata per diminuirne il 
 peso mantenendone la qualità prima di essere registrata.
 Inoltre sono estratte le informazioni dalla foto e vengono 
 registrate su DB. Infine viene generato un thumbnail.

 Questa classe usa Imagick, una estensione di PHP che deve
 essere installata sul sistema operativo.
  
 * -------------------------------------------------------*
 * CLASS PATH:       App\Flussu\Documents
 * CLASS-NAME:       Res_img
 * -------------------------------------------------------*
 * CREATED DATE:    06.03.2021
 * VERSION REL.:     4.2.20250625
 * UPDATES DATE:     25.02:2025 
 * - - - - - - - - - - - - - - - - - - - - - - - - - - - -*
 * Releases/Updates:
 * -------------------------------------------------------*/
/**
 * BASED ON:
 * An Imagick extension to provide better (higher quality, lower file size) image resizes.
 *
 * This class extends Imagick (<http://php.net/manual/en/book.imagick.php>) based on
 * research into optimal image resizing techniques (<https://github.com/nwtn/image-resize-tests>).
 *
 * Using these methods with their default settings should provide image resizing that is
 * visually indistinguishable from Photoshop’s “Save for Web…”, but at lower file sizes.
 *
 * @author		David Newton <david@davidnewton.ca>
 * @copyright	2015 David Newton
 * @license		https://raw.githubusercontent.com/nwtn/php-respimg/master/LICENSE MIT
 * @version		1.0.1
 */


/**
 * The Res_img class is responsible for manipulating images before they are processed by the Flussu server.
 * 
 * This class extends the Imagick class and provides additional functionality to optimize and manipulate images.
 * It ensures that images are correctly oriented, resized, and optimized for quality and file size before being
 * stored or further processed. Additionally, it extracts metadata from images and generates thumbnails.
 * 
 * Key responsibilities of the Res_img class include:
 * - Correcting the orientation of images taken with rotated cameras.
 * - Optimizing images to reduce file size while maintaining quality.
 * - Extracting metadata from images and storing it in the database.
 * - Generating thumbnails for images.
 * - Utilizing external programs such as SVGO and image_optim for further optimization.
 * 
 * The class leverages the Imagick extension, which must be installed on the system, to perform these operations.
 * It is designed to provide high-quality image processing that is efficient and effective.
 * 
 * @package App\Flussu\Documents
 */


 namespace Flussu\Documents;
use Flussu\General;

class Res_img extends \Imagick {
	/**
	 * Optimizes the image without reducing quality.
	 *
	 * This function calls up to four external programs, which must be installed and available in the $PATH:
	 *
	 * * SVGO
	 * * image_optim
	 * * picopt
	 * * ImageOptim
	 *
	 * Note that these are executed using PHP’s `exec` command, so there may be security implications.
	 *
	 * @access	public
	 *
	 * @param	string	$path			The path to the file or directory that should be optimized.
	 * @param	integer	$svgo			The number of times to optimize using SVGO.
	 * @param	integer	$image_optim	The number of times to optimize using image_optim.
	 * @param	integer	$picopt			The number of times to optimize using picopt.
	 * @param	integer	$imageOptim		The number of times to optimize using ImageOptim.
	 */

	public static function optimize($path, $svgo = 0, $image_optim = 0, $picopt = 0, $imageOptim = 0) {
		// make sure the path is real
		if (!file_exists($path)) {
			return false;
		}
		$is_dir = is_dir($path);
		if (!$is_dir) {
			$dir = escapeshellarg(substr($path, 0, strrpos($path, '/')));
			$file = escapeshellarg(substr($path, strrpos($path, '/') + 1));
		}
		$path = escapeshellarg($path);

		// make sure we got some ints up in here
		$svgo = (int) $svgo;
		$image_optim = (int) $image_optim;
		$picopt = (int) $picopt;
		$imageOptim = (int) $imageOptim;

		// create some vars to store output
		$output = array();
		$return_var = 0;

		// if we’re using image_optim, we need to create the YAML config file
		if ($image_optim > 0) {
			$yml = tempnam('/tmp', 'yml');
			file_put_contents($yml, "verbose: true\njpegtran:\n  progressive: false\noptipng:\n  level: 7\n  interlace: false\npngcrush:\n  fix: true\n  brute: true\npngquant:\n  speed: 11\n");
		}

		// do the svgo optimizations
		for ($i = 0; $i < $svgo; $i++) {
			if ($is_dir) {
				$command = escapeshellcmd('svgo -f ' . $path . ' --disable removeUnknownsAndDefaults');
			} else {
				$command = escapeshellcmd('svgo -i ' . $path . ' --disable removeUnknownsAndDefaults');
			}
			exec($command, $output, $return_var);

			if ($return_var != 0) {
				return false;
			}
		}

		// do the image_optim optimizations
		for ($i = 0; $i < $image_optim; $i++) {
			$command = escapeshellcmd('image_optim -r ' . $path . ' --config-paths ' . $yml);
			exec($command, $output, $return_var);

			if ($return_var != 0) {
				return false;
			}
		}

		// do the picopt optimizations
		for ($i = 0; $i < $picopt; $i++) {
			$command = escapeshellcmd('picopt -r ' . $path);
			exec($command, $output, $return_var);

			if ($return_var != 0) {
				return false;
			}
		}

		// do the ImageOptim optimizations
		// ImageOptim can’t handle the path with single quotes, so we have to strip them
		// ImageOptim-CLI has an issue where it only works with a directory, not a single file
		for ($i = 0; $i < $imageOptim; $i++) {
			if ($is_dir) {
				$command = escapeshellcmd('imageoptim -d ' . $path . ' -q');
			} else {
				$command = escapeshellcmd('find ' . $dir . ' -name ' . $file) . ' | imageoptim';
			}
			exec($command, $output, $return_var);

			if ($return_var != 0) {
				return false;
			}
		}
		return $output;
	}

	/**
	 * Resizes the image using smart defaults for high quality and low file size.
	 *
	 * This function is basically equivalent to:
	 *
	 * $optim == true: `mogrify -path OUTPUT_PATH -filter Triangle -define filter:support=2.0 -thumbnail OUTPUT_WIDTH -unsharp 0.25x0.08+8.3+0.045 -dither None -posterize 136 -quality 82 -define jpeg:fancy-upsampling=off -define png:compression-filter=5 -define png:compression-level=9 -define png:compression-strategy=1 -define png:exclude-chunk=all -interlace none -colorspace sRGB INPUT_PATH`
	 *
	 * $optim == false: `mogrify -path OUTPUT_PATH -filter Triangle -define filter:support=2.0 -thumbnail OUTPUT_WIDTH -unsharp 0.25x0.25+8+0.065 -dither None -posterize 136 -quality 82 -define jpeg:fancy-upsampling=off -define png:compression-filter=5 -define png:compression-level=9 -define png:compression-strategy=1 -define png:exclude-chunk=all -interlace none -colorspace sRGB -strip INPUT_PATH`
	 *
	 * @access	public
	 *
	 * @param	integer	$columns		The number of columns in the output image. 0 = maintain aspect ratio based on $rows.
	 * @param	integer	$rows			The number of rows in the output image. 0 = maintain aspect ratio based on $columns.
	 * @param	bool	$optim			Whether you intend to perform optimization on the resulting image. Note that setting this to `true` doesn’t actually perform any optimization.
	 */
	public function smartResize($columns, $rows, $optim = false) {
		$this->setOption('filter:support', '2.0');
		$this->thumbnailImage($columns, $rows, false, false, \Imagick::FILTER_TRIANGLE);
		if ($optim) {
			$this->unsharpMaskImage(0.25, 0.08, 8.3, 0.045);
		} else {
			$this->unsharpMaskImage(0.25, 0.25, 8, 0.065);
		}
		$this->posterizeImage(136, false);
		$this->setImageCompressionQuality(82);
		$this->setOption('jpeg:fancy-upsampling', 'off');
		$this->setOption('png:compression-filter', '5');
		$this->setOption('png:compression-level', '9');
		$this->setOption('png:compression-strategy', '1');
		$this->setOption('png:exclude-chunk', 'all');
		$this->setInterlaceScheme(\Imagick::INTERLACE_NO);
		$this->setColorspace(\Imagick::COLORSPACE_SRGB);
		if (!$optim) {
			$this->stripImage();
		}
	}

	/**
	 * Changes the size of an image to the given dimensions and removes any associated profiles.
	 *
	 * `thumbnailImage` changes the size of an image to the given dimensions and
	 * removes any associated profiles.  The goal is to produce small low cost
	 * thumbnail images suited for display on the Web.
	 *
	 * With the original Imagick thumbnailImage implementation, there is no way to choose a
	 * resampling filter. This class recreates Imagick’s C implementation and adds this
	 * additional feature.
	 *
	 * Note: <https://github.com/mkoppanen/imagick/issues/90> has been filed for this issue.
	 *
	 * @access	public
	 *
	 * @param	integer	$columns		The number of columns in the output image. 0 = maintain aspect ratio based on $rows.
	 * @param	integer	$rows			The number of rows in the output image. 0 = maintain aspect ratio based on $columns.
	 * @param	bool	$bestfit		Treat $columns and $rows as a bounding box in which to fit the image.
	 * @param	bool	$fill			Fill in the bounding box with the background colour.
	 * @param	integer	$filter			The resampling filter to use. Refer to the list of filter constants at <http://php.net/manual/en/imagick.constants.php>.
	 *
	 * @return	bool	Indicates whether the operation was performed successfully.
	 */


	 public function thumbnailImage(?int $columns, ?int $rows, bool $bestfit = false, bool $fill = false, bool $legacy = false):bool {
	 //public function thumbnailImage($columns, $rows, $bestfit = false, $fill = false, $filter = \Imagick::FILTER_TRIANGLE) 

		// sample factor; defined in original ImageMagick thumbnailImage function
		// the scale to which the image should be resized using the `sample` function
		$SampleFactor = 5;

		// filter whitelist
		$filters = array(
			\Imagick::FILTER_POINT,
			\Imagick::FILTER_BOX,
			\Imagick::FILTER_TRIANGLE,
			\Imagick::FILTER_HERMITE,
			\Imagick::FILTER_HANNING,
			\Imagick::FILTER_HAMMING,
			\Imagick::FILTER_BLACKMAN,
			\Imagick::FILTER_GAUSSIAN,
			\Imagick::FILTER_QUADRATIC,
			\Imagick::FILTER_CUBIC,
			\Imagick::FILTER_CATROM,
			\Imagick::FILTER_MITCHELL,
			\Imagick::FILTER_LANCZOS,
			\Imagick::FILTER_BESSEL,
			\Imagick::FILTER_SINC
		);

		// Parse parameters given to function
		$columns = (double) ($columns);
		$rows = (double) ($rows);
		$bestfit = (bool) $bestfit;
		$fill = (bool) $fill;

		// We can’t resize to (0,0)
		if ($rows < 1 && $columns < 1) {
			return false;
		}

		// Set a default filter if an acceptable one wasn’t passed
		//if (!in_array($filter, $filters)) {
			$filter = \Imagick::FILTER_TRIANGLE;
		//}

		// figure out the output width and height
		$width = (double) $this->getImageWidth();
		$height = (double) $this->getImageHeight();
		$new_width = $columns;
		$new_height = $rows;

		$x_factor = $columns / $width;
		$y_factor = $rows / $height;
		if ($rows < 1) {
			$new_height = round($x_factor * $height);
		} elseif ($columns < 1) {
			$new_width = round($y_factor * $width);
		}

		// if bestfit is true, the new_width/new_height of the image will be different than
		// the columns/rows parameters; those will define a bounding box in which the image will be fit
		if ($bestfit && $x_factor > $y_factor) {
			$x_factor = $y_factor;
			$new_width = round($y_factor * $width);
		} elseif ($bestfit && $y_factor > $x_factor) {
			$y_factor = $x_factor;
			$new_height = round($x_factor * $height);
		}
		if ($new_width < 1) {
			$new_width = 1;
		}
		if ($new_height < 1) {
			$new_height = 1;
		}

		// if we’re resizing the image to more than about 1/3 it’s original size
		// then just use the resize function
		if (($x_factor * $y_factor) > 0.1) {
			$this->resizeImage($new_width, $new_height, $filter, 1);

		// if we’d be using sample to scale to smaller than 128x128, just use resize
		} elseif ((($SampleFactor * $new_width) < 128) || (($SampleFactor * $new_height) < 128)) {
				$this->resizeImage($new_width, $new_height, $filter, 1);

		// otherwise, use sample first, then resize
		} else {
			$this->sampleImage($SampleFactor * $new_width, $SampleFactor * $new_height);
			$this->resizeImage($new_width, $new_height, $filter, 1);
		}


		// if the alpha channel is not defined, make it opaque
		if ($this->getImageAlphaChannel() == \Imagick::ALPHACHANNEL_UNDEFINED) {
/* ALDUS MODIFY */
						if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
							$this->setImageAlphaChannel(\Imagick::ALPHACHANNEL_OFF);
						} else {
							$this->setImageAlphaChannel(\Imagick::ALPHACHANNEL_OPAQUE);
						}
		}

		// set the image’s bit depth to 8 bits
		$this->setImageDepth(8);

		// turn off interlacing
		$this->setInterlaceScheme(\Imagick::INTERLACE_NO);

		// Strip all profiles except color profiles.
		foreach ($this->getImageProfiles('*', true) as $key => $value) {
			if ($key != 'icc' && $key != 'icm') {
				try{
					$this->removeImageProfile($key);
				} catch (\Throwable $e){}
			}
		}

		if (method_exists($this, 'deleteImageProperty')) {
			$this->deleteImageProperty('comment');
			$this->deleteImageProperty('Thumb::URI');
			$this->deleteImageProperty('Thumb::MTime');
			$this->deleteImageProperty('Thumb::Size');
			$this->deleteImageProperty('Thumb::Mimetype');
			$this->deleteImageProperty('software');
			$this->deleteImageProperty('Thumb::Image::Width');
			$this->deleteImageProperty('Thumb::Image::Height');
			$this->deleteImageProperty('Thumb::Document::Pages');
		} else {
			$this->setImageProperty('comment', '');
			$this->setImageProperty('Thumb::URI', '');
			$this->setImageProperty('Thumb::MTime', '');
			$this->setImageProperty('Thumb::Size', '');
			$this->setImageProperty('Thumb::Mimetype', '');
			$this->setImageProperty('software', '');
			$this->setImageProperty('Thumb::Image::Width', '');
			$this->setImageProperty('Thumb::Image::Height', '');
			$this->setImageProperty('Thumb::Document::Pages', '');
		}

		// In case user wants to fill use extent for it rather than creating a new canvas
		// …fill out the bounding box
		if ($bestfit && $fill && ($new_width != $columns || $new_height != $rows)) {
			$extent_x = 0;
			$extent_y = 0;

			if ($columns > $new_width) {
				$extent_x = ($columns - $new_width) / 2;
			}
			if ($rows > $new_height) {
				$extent_y = ($rows - $new_height) / 2;
			}

			$this->extentImage($columns, $rows, 0 - $extent_x, $extent_y);
		}
		return true;
	}


	private $position = 0;
    private $items = [];

    public function __construct(array $items)
    {
        $this->items = $items;
        $this->position = 0;
    }

    // Restituisce l'elemento corrente
    public function current(): mixed
    {
        return $this->items[$this->position];
    }

    // Restituisce la chiave corrente
    public function key(): mixed
    {
        return $this->position;
    }

    // Avanza il puntatore interno all'elemento successivo
    public function next(): void
    {
        ++$this->position;
    }

    // Riporta il puntatore all'inizio
    public function rewind(): void
    {
        $this->position = 0;
    }

    // Verifica se la posizione corrente è valida
    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }
}
