<?php

namespace SpaanProductions\LaravelSwitchbotFrame\Actions;

use GdImage;
use Throwable;
use RuntimeException;

/**
 * Prepares images for the SwitchBot AI Art Frame's E Ink Spectra 6 panel.
 *
 * The panel is 1600x1200 and only renders six muted inks, so straight photo
 * uploads come out dark and dull. This action crops to fill the panel and
 * applies a saturation, contrast, brightness and sharpening boost. Dithering is
 * intentionally left to the SwitchBot side to avoid double-dithering.
 */
class OptimizeImageForEink
{
	public const int PANEL_LONG_EDGE = 1600;

	public const int PANEL_SHORT_EDGE = 1200;

	public const float SATURATION_BOOST = 1.6;

	public const int CONTRAST_BOOST = -18;

	public const int BRIGHTNESS_BOOST = 12;

	public const int JPEG_QUALITY = 92;

	public function __construct(
		private float $saturation = self::SATURATION_BOOST,
		private int $contrast = self::CONTRAST_BOOST,
		private int $brightness = self::BRIGHTNESS_BOOST,
		private bool $sharpen = true,
		private int $jpegQuality = self::JPEG_QUALITY,
	) {
	}

	/** Build an instance from the package's configured default optimizer preset. */
	public static function fromConfig(): self
	{
		$presets = config('switchbot.optimizer.presets');
		$default = (string) config('switchbot.optimizer.default', 'vivid');

		$preset = is_array($presets) && isset($presets[$default]) && is_array($presets[$default])
			? $presets[$default]
			: [];

		return static::fromArray($preset);
	}

	/**
	 * Build an instance from a preset array (e.g. a `switchbot.optimizer.presets` entry).
	 *
	 * @param array<string, mixed> $settings
	 */
	public static function fromArray(array $settings): self
	{
		return new self(
			(float) ($settings['saturation'] ?? self::SATURATION_BOOST),
			(int) ($settings['contrast'] ?? self::CONTRAST_BOOST),
			(int) ($settings['brightness'] ?? self::BRIGHTNESS_BOOST),
			(bool) ($settings['sharpen'] ?? true),
			(int) ($settings['jpeg_quality'] ?? self::JPEG_QUALITY),
		);
	}

	/**
	 * Process the image at $sourcePath and write a JPEG to $targetPath.
	 *
	 * Landscape sources become 1600x1200, portrait sources 1200x1600. With
	 * $enhance disabled only the crop-to-fill resize is applied.
	 *
	 * @return array{width: int, height: int}
	 */
	public function handle(string $sourcePath, string $targetPath, bool $enhance = true): array
	{
		$source = $this->load($sourcePath);
		$source = $this->applyExifOrientation($source, $sourcePath);

		[$targetWidth, $targetHeight] = imagesx($source) >= imagesy($source)
			? [self::PANEL_LONG_EDGE, self::PANEL_SHORT_EDGE]
			: [self::PANEL_SHORT_EDGE, self::PANEL_LONG_EDGE];

		$canvas = $this->cropToFill($source, $targetWidth, $targetHeight);

		unset($source);

		if ($enhance) {
			$this->boostSaturation($canvas, $this->saturation);
			imagefilter($canvas, IMG_FILTER_CONTRAST, $this->contrast);
			imagefilter($canvas, IMG_FILTER_BRIGHTNESS, $this->brightness);

			if ($this->sharpen) {
				$this->applySharpen($canvas);
			}
		}

		if ( ! @imagejpeg($canvas, $targetPath, $this->jpegQuality)) {
			unset($canvas);

			throw new RuntimeException(sprintf('Could not write optimized image to [%s].', $targetPath));
		}

		unset($canvas);

		return ['width' => $targetWidth, 'height' => $targetHeight];
	}

	private function load(string $path): GdImage
	{
		$contents = @file_get_contents($path);

		if ($contents === false) {
			throw new RuntimeException(sprintf('Could not read image at [%s].', $path));
		}

		$image = @imagecreatefromstring($contents);

		if ($image === false) {
			throw new RuntimeException(sprintf('The file at [%s] is not a valid image.', $path));
		}

		return $image;
	}

	/**
	 * Re-orient the image according to its EXIF Orientation flag.
	 *
	 * `imagecreatefromstring` ignores EXIF, so phone photos taken in portrait
	 * (which store landscape pixels plus an Orientation flag) would otherwise be
	 * cropped sideways. This normalises the pixel data to a natural orientation.
	 */
	private function applyExifOrientation(GdImage $image, string $sourcePath): GdImage
	{
		if ( ! extension_loaded('exif')) {
			return $image;
		}

		try {
			$exif = @exif_read_data($sourcePath);
		} catch (Throwable) {
			return $image;
		}

		if ( ! is_array($exif) || ! isset($exif['Orientation'])) {
			return $image;
		}

		$orientation = (int) $exif['Orientation'];

		if ($orientation === 1) {
			return $image;
		}

		/**
		 * EXIF orientation to GD transform. `imagerotate` rotates
		 * counter-clockwise and returns a new handle; `imageflip` mutates in
		 * place. Orientations 5 and 7 need both a flip and a rotation.
		 */
		switch ($orientation) {
			case 2:
				imageflip($image, IMG_FLIP_HORIZONTAL);

				break;
			case 3:
				$image = $this->rotate($image, 180);

				break;
			case 4:
				imageflip($image, IMG_FLIP_VERTICAL);

				break;
			case 5:
				imageflip($image, IMG_FLIP_VERTICAL);
				$image = $this->rotate($image, -90);

				break;
			case 6:
				$image = $this->rotate($image, -90);

				break;
			case 7:
				imageflip($image, IMG_FLIP_HORIZONTAL);
				$image = $this->rotate($image, -90);

				break;
			case 8:
				$image = $this->rotate($image, 90);

				break;
		}

		return $image;
	}

	/** Rotate counter-clockwise, freeing the previous handle that GD replaces. */
	private function rotate(GdImage $image, int $angle): GdImage
	{
		$rotated = imagerotate($image, $angle, 0);

		if ($rotated === false) {
			return $image;
		}

		unset($image);

		return $rotated;
	}

	/** Scale and centre-crop the source so it completely fills the target size. */
	private function cropToFill(GdImage $source, int $targetWidth, int $targetHeight): GdImage
	{
		$sourceWidth = imagesx($source);
		$sourceHeight = imagesy($source);

		$scale = max($targetWidth / $sourceWidth, $targetHeight / $sourceHeight);

		$cropWidth = (int) round($targetWidth / $scale);
		$cropHeight = (int) round($targetHeight / $scale);

		$cropX = (int) max(0, round(($sourceWidth - $cropWidth) / 2));
		$cropY = (int) max(0, round(($sourceHeight - $cropHeight) / 2));

		$canvas = imagecreatetruecolor($targetWidth, $targetHeight);

		imagecopyresampled(
			$canvas,
			$source,
			0,
			0,
			$cropX,
			$cropY,
			$targetWidth,
			$targetHeight,
			$cropWidth,
			$cropHeight,
		);

		return $canvas;
	}

	/**
	 * Push each pixel away from its luma to compensate for the panel's muted
	 * six-ink gamut. GD has no native saturation filter.
	 */
	private function boostSaturation(GdImage $image, float $factor): void
	{
		$width = imagesx($image);
		$height = imagesy($image);

		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				$rgb = imagecolorat($image, $x, $y);

				$red = ($rgb >> 16) & 0xFF;
				$green = ($rgb >> 8) & 0xFF;
				$blue = $rgb & 0xFF;

				$luma = 0.299 * $red + 0.587 * $green + 0.114 * $blue;

				$red = (int) min(255, max(0, $luma + ($red - $luma) * $factor));
				$green = (int) min(255, max(0, $luma + ($green - $luma) * $factor));
				$blue = (int) min(255, max(0, $luma + ($blue - $luma) * $factor));

				imagesetpixel($image, $x, $y, ($red << 16) | ($green << 8) | $blue);
			}
		}
	}

	/** Mild convolution to recover detail lost to dithering. */
	private function applySharpen(GdImage $image): void
	{
		imageconvolution($image, [
			[-1.0, -1.0, -1.0],
			[-1.0, 20.0, -1.0],
			[-1.0, -1.0, -1.0],
		], 12.0, 0.0);
	}
}
