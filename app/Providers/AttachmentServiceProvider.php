<?php

namespace App\Providers;

use App\Model\Attachment;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use FFMpeg\Filters\Video\CustomFilter;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Facades\Image;
use ProtoneMedia\LaravelFFMpeg\Filters\WatermarkFactory;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Ramsey\Uuid\Uuid;

class AttachmentServiceProvider extends ServiceProvider
{

    public static $videoEncodingPresets = [
        'size' => ['videoBitrate'=> 500, 'audioBitrate' => 128],
        'balanced' => ['videoBitrate'=> 1000, 'audioBitrate' => 256],
        'quality' => ['videoBitrate'=> 2000, 'audioBitrate' => 512],
    ];

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Filter attachments by their extension.
     *
     * @param bool $type
     * @return bool|\Illuminate\Config\Repository|mixed|string|null
     */
    public static function filterExtensions($type = false)
    {
        if ($type) {
            switch ($type) {
                case 'videosFallback':
                    if (getSetting('media.enable_ffmpeg')) {
                        return getSetting('media.allowed_file_extensions');
                    } else {
                        $extensions = explode(',', getSetting('media.allowed_file_extensions'));
                        $extensions = array_diff($extensions, self::getTypeByExtension('video'));
                        $extensions[] = 'mp4';
                        return implode(',', $extensions);
                    }
                    break;
                case 'imagesOnly':
                    return implode(',', self::getTypeByExtension('images'));
                    break;
                case 'manualPayments':
                    return 'jpg,jpeg,png,pdf,xls,xlsx';
                    break;
            }
        }

        return false;
    }

    /**
     * Get attachment type by extension.
     *
     * @param $type
     * @return string
     */
    public static function getAttachmentType($type)
    {
        switch ($type) {
            case 'avi':
            case 'mp4':
            case 'wmw':
            case 'mpeg':
            case 'm4v':
            case 'moov':
            case 'mov':
            case 'mkv':
            case 'wmv':
            case 'asf':
                return 'video';
                break;
            case 'mp3':
            case 'wav':
            case 'ogg':
                return 'audio';
                break;
            case 'png':
            case 'jpg':
            case 'jpeg':
                return 'image';
            case 'pdf':
            case 'xls':
            case 'xlsx':
                return 'document';
                break;
            default:
                return 'image';
                break;
        }
    }

    /**
     * Get file extensions by types.
     *
     * @param $type
     * @return array
     */
    public static function getTypeByExtension($type)
    {
        switch ($type) {
            case 'video':
                return ['mp4', 'avi', 'wmv', 'mpeg', 'm4v', 'moov', 'mov','mkv','asf'];
                break;
            case 'audio':
                return ['mp3', 'wav', 'ogg'];
                break;
            default:
                return ['jpg', 'jpeg', 'png'];
                break;
        }
    }

    /**
     * Return matching bookmarks category types to actual attachment types.
     *
     * @param $type
     * @return bool|string
     */
    public static function getActualTypeByBookmarkCategory($type)
    {
        switch ($type) {
            case 'photos':
                return 'image';
                break;
            case 'audio':
                return 'audio';
                break;
            case 'videos':
                return 'video';
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * Creates attachment, filter it and uploads to the storage disk.
     *
     * @param $file
     * @param $directory
     * @param $generateThumbnail
     * @return mixed
     * @throws \Exception
     */
    public static function createAttachment($file, $directory, $generateThumbnail)
    {

        $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
        do {
            $fileId = Uuid::uuid4()->getHex();
        } while (Attachment::query()->where('id', $fileId)->first() != null);

        $fileExtension = $initialFileExtension = $file->guessExtension();
        $fileContent = file_get_contents($file);
        $filePath = $directory.'/'.$fileId.'.'.$fileExtension;

        // Converting all images to jpegs
        if (self::getAttachmentType($fileExtension) == 'image') {
            $jpgImage = Image::make($file);
            $jpgImage->fit($jpgImage->width(), $jpgImage->height())->orientate();

            if (getSetting('media.apply_watermark')) {
                // Add watermark to post images

                if(getSetting('media.watermark_image')){
                    $watermark = Image::make(self::getWatermarkPath());
                    $resizePercentage = 75; //70% less then an actual image (play with this value)
                    $watermarkSize = round($jpgImage->width() * ((100 - $resizePercentage) / 100), 2); //watermark will be $resizePercentage less then the actual width of the image
                    // resize watermark width keep height auto
                    $watermark->resize($watermarkSize, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                    $jpgImage->insert($watermark, 'bottom-right', 30, 25);
                }

                if(getSetting('media.use_url_watermark')) {
                    $textWaterMark = str_replace(['https://', 'http://', 'www.'], '', route('profile', ['username' => Auth::user()->username]));
                    $textWaterMarkSize = 3 / 100 * $jpgImage->width();
                    $jpgImage->text($textWaterMark, $jpgImage->width() - 25, $jpgImage->height() - 10, function ($font) use ($textWaterMarkSize) {
                        $font->file(public_path('/fonts/OpenSans-Semibold.ttf'));
                        $font->size($textWaterMarkSize);
                        $font->color(array(255, 255, 255, 0.7));
                        $font->align('right');
                        $font->valign('bottom');
                        $font->angle(0);
                    });
                }
            }

            // No processing for gifs
            // TODO: Add watermarking via other lib - intervention has no support for it
            if($fileExtension == 'gif'){
                $fileExtension = 'gif';
                $fileContent = $file;
                $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
                $storage->put($filePath, file_get_contents($file->getRealPath()), 'public');
            }
            else{
                // Saving rest of image types
                $jpgImage->encode('jpg', 100);
                $file = $jpgImage;
                $fileExtension = 'jpg';
                $fileContent = $file;
                $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
                // Uploading to storage
                $storage->put($filePath, $fileContent, 'public');
            }

        }

        // generate thumbnail
        if ($generateThumbnail && self::getAttachmentType($fileExtension) === 'image') {
            $width = 150;
            $height = 150;
            $img = Image::make($file);
            $img->fit(150, 150, function ($constraint) {
                $constraint->aspectRatio();
            });
            $img->encode('jpg', 100);

            $thumbnailDir = $directory.'/'.$width.'X'.$height;
            $thumbnailfilePath = $thumbnailDir.'/'.$fileId.'.jpg';
            // Uploading to storage
            $storage->put($thumbnailfilePath, $img, 'public');
        }

        // Convert videos to mp4s
        if (self::getAttachmentType($fileExtension) === 'video') {

            $storage->put($filePath, file_get_contents($file->getRealPath()), 'public');

        /*
            if (getSetting('media.enable_ffmpeg')) {
                // Move tmp file onto local files path, as ffmpeg can't handle absolute paths
                $filePath = $fileId.'.'.$fileExtension;
                Storage::disk('tmp')->put($filePath, $fileContent);

                $fileExtension = 'mp4';
                $newfilePath = $directory.'/'.$fileId.'.'.$fileExtension;

                // Converting the video
                $video = FFMpeg::
                fromDisk('tmp')
                    ->open($filePath);

                // Checking if uploaded videos do no exceed maximum length in seconds
                if(getSetting('media.max_videos_length')){
                    $maxLength = (int)getSetting('media.max_videos_length');
                    $videoLength = $video->getFormat()->get('duration');
                    $videoLength = explode('.',$videoLength);
                    $videoLength = (int)$videoLength[0];
                    if($videoLength > $maxLength){
                        throw new \Exception(__("Uploaded videos can not longer than :length seconds.",['length'=>$maxLength]));
                    }
                }

                // Add watermark if enabled in admin
                if (getSetting('media.apply_watermark')) {
                    $dimensions = $video
                        ->getVideoStream()
                        ->getDimensions();
                    if(getSetting('media.watermark_image')) {
                        // Add watermark to post images
                        $watermark = Image::make(self::getWatermarkPath());
                        $tmpWatermarkFile = 'watermark-' . $fileId . '-.png';
                        $resizePercentage = 75; //70% less then an actual image (play with this value)
                        $watermarkSize = round($dimensions->getWidth() * ((100 - $resizePercentage) / 100), 2); //watermark will be $resizePercentage less then the actual width of the image
                        // resize watermark width keep height auto
                        $watermark->resize($watermarkSize, null, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                        $watermark->encode('png', 100);
                        Storage::disk('tmp')->put($tmpWatermarkFile, $watermark);
                        if (getSetting('media.apply_watermark')) {
                            $video->addWatermark(function (WatermarkFactory $watermark) use ($fileId, $tmpWatermarkFile) {
                                $watermark->fromDisk('tmp')
                                    ->open($tmpWatermarkFile)
                                    ->right(25)
                                    ->bottom(25);
                            });
                        }
                    }

                    if(getSetting('media.use_url_watermark')){
                        $textWaterMark = str_replace(['https://','http://','www.'],'',route('profile',['username'=>Auth::user()->username]));
                        $textWaterMarkSize = 3 / 100 * $dimensions->getWidth();
                        // Note: Some hosts might need to default font on public_path('/fonts/OpenSans-Semibold.ttf') instead of verdana
                        $filter = new CustomFilter("drawtext=text='".$textWaterMark."':x=10:y=H-th-10:fontfile='".(env('FFMPEG_FONT_PATH') ?? 'Verdana')."':fontsize={$textWaterMarkSize}:fontcolor=white: x=(w-text_w)-25: y=(h-text_h)-35");
                        $video->addFilter($filter);
                    }

                }

                // Re-converting mp4 only if enforced by the admin setting
                if($initialFileExtension == 'mp4' && !getSetting('media.enforce_mp4_conversion')){
                    $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
                    $storage->put($filePath, $fileContent, 'public');
                }
                else{
                    // Overriding default ffmpeg lib temporary_files_root behaviour
                    $ffmpegOutputLogDir = storage_path() . '/logs/ffmpeg';
                    $ffmpegPassFile = $ffmpegOutputLogDir . '/' . uniqid();
                    if(!is_dir($ffmpegOutputLogDir)){
                        mkdir($ffmpegOutputLogDir);
                    }

                    $videoQualityPreset = self::$videoEncodingPresets[getSetting('media.ffmpeg_video_conversion_quality_preset')];
                    $video = $video->export()
                        ->toDisk(config('filesystems.defaultFilesystemDriver'));
                    if(getSetting('media.ffmpeg_audio_encoder') == 'aac'){
                        $video->inFormat((new X264('aac', 'libx264'))->setKiloBitrate($videoQualityPreset['videoBitrate'])->setAudioKiloBitrate($videoQualityPreset['audioBitrate']));
                    }
                    elseif(getSetting('media.ffmpeg_audio_encoder') == 'libmp3lame'){
                        $video->inFormat((new X264('libmp3lame'))->setKiloBitrate($videoQualityPreset['videoBitrate'])->setAudioKiloBitrate($videoQualityPreset['audioBitrate']));
                    }
                    elseif (getSetting('media.ffmpeg_audio_encoder') == 'libfdk_aac'){
                        $video->inFormat((new X264('libfdk_aac', 'libx264'))->setKiloBitrate($videoQualityPreset['videoBitrate'])->setAudioKiloBitrate($videoQualityPreset['audioBitrate']));
                    }
                    $video->addFilter('-preset', 'ultrafast')
                        #->addFilter(['-strict', 2])
                        ->addFilter(['-passlogfile', $ffmpegPassFile])
                        ->save($newfilePath);

                    if(file_exists($ffmpegPassFile.'-0.log')) unlink($ffmpegPassFile.'-0.log');
                    if(file_exists($ffmpegPassFile.'-1.log')) unlink($ffmpegPassFile.'-1.log');

                }

                Storage::disk('tmp')->delete($filePath);
                if (getSetting('media.apply_watermark') && getSetting('media.watermark_image')) {
                    Storage::disk('tmp')->delete($tmpWatermarkFile);
                }
                $filePath = $newfilePath;
            } else {
                $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
                $storage->put($filePath, $fileContent, 'public');
            }
            */
            //TODO: Create preview for clip
        }

        if (in_array(self::getAttachmentType($fileExtension), ['audio', 'document'])) {
            $filePath = $directory.'/'.$fileId.'.'.$fileExtension;
            $storage->put($filePath, $fileContent, 'public');
        }

        // Creating the db entry
        $storageDriver = config('filesystems.defaultFilesystemDriver');
        $attachment = Attachment::create([
            'id' => $fileId,
            'filename' => $filePath,
            'user_id' => Auth::id(),
            'type' => $fileExtension,
            'driver' => AttachmentServiceProvider::getStorageProviderID($storageDriver),
        ]);

        return $attachment;
    }

    /**
     * Method used to return real watermark path / fallback to the default one.
     *
     * @return mixed|string
     */
    public static function getWatermarkPath()
    {
        $watermark_image = getSetting('media.watermark_image');
        if($watermark_image){
            if (strpos($watermark_image, 'download_link')) {
                $watermark_image = json_decode($watermark_image);
                if ($watermark_image) {
                    $watermark_image = Storage::disk(config('filesystems.defaultFilesystemDriver'))->path($watermark_image[0]->download_link);
                }
            }
        }
        else{
            $watermark_image = public_path('img/logo-black.png');
        }
        return $watermark_image;
    }

    /**
     * Gets thumbnail path by resolution.
     *
     * @param $attachment
     * @param $width
     * @param $height
     * @param string $basePath
     * @return string|string[]
     */
    public static function getThumbnailPathForAttachmentByResolution($attachment, $width, $height, $basePath = '/posts/images/')
    {
        if ($attachment->driver == Attachment::S3_DRIVER && getSetting('storage.aws_cdn_enabled') && getSetting('storage.aws_cdn_presigned_urls_enabled')) {
            return self::signAPrivateDistributionPolicy(
                'https://' . getSetting('storage.cdn_domain_name') . '/' . self::getThumbnailFilenameByAttachmentAndResolution($attachment, $width, $height, $basePath)
            );
        } else {
            return str_replace($basePath, $basePath.$width.'X'.$height.'/', $attachment->path);
        }
    }

    /**
     * Removes attachment from storage disk.
     *
     * @param $attachment
     */
    public static function removeAttachment($attachment)
    {
        $storage = Storage::disk(self::getStorageProviderName($attachment->driver));
        $storage->delete($attachment->filename);
        if (self::getAttachmentType($attachment->type) == 'image') {
            $thumbnailPath = self::getThumbnailFilenameByAttachmentAndResolution($attachment, $width = 150, $height = 150);

            if ($thumbnailPath != null) {
                $storage->delete($thumbnailPath);
            }
        }
    }

    /**
     * Returns file thumbnail path, by resolution.
     *
     * @param $attachment
     * @param $width
     * @param $height
     * @return string|string[]
     */
    private static function getThumbnailFilenameByAttachmentAndResolution($attachment, $width, $height, $basePath = 'posts/images/')
    {
        return str_replace($basePath, $basePath.$width.'X'.$height.'/', $attachment->filename);
    }

    /**
     * Returns file path by attachment.
     *
     * @param $attachment
     * @return string
     */
    public static function getFilePathByAttachment($attachment)
    {

        // Changing to attachment file system driver, if different from the configured one
        if($attachment->driver !== self::getStorageProviderID(getSetting('storage.driver'))){
            $oldDriver = config('filesystems.default');
            SettingsServiceProvider::setDefaultStorageDriver(self::getStorageProviderName($attachment->driver));
        }

        $fileUrl = '';
        if ($attachment->driver == Attachment::S3_DRIVER) {
            if (getSetting('storage.aws_cdn_enabled') && getSetting('storage.aws_cdn_presigned_urls_enabled')) {
                $fileUrl = self::signAPrivateDistributionPolicy(
                    'https://'.getSetting('storage.cdn_domain_name').'/'.$attachment->filename
                );
            } elseif (getSetting('storage.aws_cdn_enabled')) {
                $fileUrl = 'https://'.getSetting('storage.cdn_domain_name').'/'.$attachment->filename;
            } else {
                $fileUrl = 'https://'.getSetting('storage.aws_bucket_name').'.s3.'.getSetting('storage.aws_region').'.amazonaws.com/'.$attachment->filename;
            }
        }
        elseif ($attachment->driver == Attachment::WAS_DRIVER || $attachment->driver == Attachment::DO_DRIVER) {
            $fileUrl = Storage::url($attachment->filename);
        }
        elseif($attachment->driver == Attachment::MINIO_DRIVER){
            $fileUrl = rtrim(getSetting('storage.minio_endpoint'), '/').'/'.getSetting('storage.minio_bucket_name').'/'.$attachment->filename;
        }
        elseif($attachment->driver == Attachment::PUSHR_DRIVER){
            $fileUrl = rtrim(getSetting('storage.pushr_cdn_hostname'), '/').'/'.$attachment->filename;
        }
        elseif ($attachment->driver == Attachment::PUBLIC_DRIVER) {
            $fileUrl = Storage::disk('public')->url($attachment->filename);
        }

        // Changing filesystem driver back, if needed
        if($attachment->driver !== self::getStorageProviderID(getSetting('storage.driver'))) {
            SettingsServiceProvider::setDefaultStorageDriver($oldDriver);
        }
        return $fileUrl;
    }

    /**
     * Method used for signing assets via CF.
     *
     * @param $cloudFrontClient
     * @param $resourceKey
     * @param $customPolicy
     * @param $privateKey
     * @param $keyPairId
     * @return mixed
     */
    private static function signPrivateDistributionPolicy(
        $cloudFrontClient,
        $resourceKey,
        $customPolicy,
        $privateKey,
        $keyPairId
    ) {
        try {
            $result = $cloudFrontClient->getSignedUrl([
                'url' => $resourceKey,
                'policy' => $customPolicy,
                'private_key' => $privateKey,
                'key_pair_id' => $keyPairId,
            ]);

            return $result;
        } catch (AwsException $e) {
        }
    }

    /**
     * Method used for signing assets via CF.
     *
     * @param $resourceKey
     * @return mixed
     */
    public static function signAPrivateDistributionPolicy($resourceKey)
    {
        $expires = time() + 24 * 60 * 60; // 24 hours (60 * 60 seconds) from now.
        $customPolicy = <<<POLICY
{
    "Statement": [
        {
            "Resource": "{$resourceKey}",
            "Condition": {
                "IpAddress": {"AWS:SourceIp": "{$_SERVER['REMOTE_ADDR']}/32"},
                "DateLessThan": {"AWS:EpochTime": {$expires}}
            }
        }
    ]
}
POLICY;
        $privateKey = base_path().'/'.getSetting('storage.aws_cdn_private_key_path');
        $keyPairId = getSetting('storage.aws_cdn_key_pair_id');

        $cloudFrontClient = new CloudFrontClient([
            'profile' => 'default',
            'version' => '2014-11-06',
            'region' => 'us-east-1',
        ]);

        return self::signPrivateDistributionPolicy(
            $cloudFrontClient,
            $resourceKey,
            $customPolicy,
            $privateKey,
            $keyPairId
        );
    }

    public static function getStorageProviderID($storageDriver){
        if($storageDriver)
            if($storageDriver == 'public'){
                return Attachment::PUBLIC_DRIVER;
            }
        if($storageDriver == 's3'){
            return Attachment::S3_DRIVER;
        }
        if($storageDriver == 'wasabi'){
            return Attachment::WAS_DRIVER;
        }
        if($storageDriver == 'do_spaces'){
            return Attachment::DO_DRIVER;
        }
        if($storageDriver == 'minio'){
            return Attachment::MINIO_DRIVER;
        }
        if($storageDriver == 'pushr'){
            return Attachment::PUSHR_DRIVER;
        }
        else{
            return Attachment::PUBLIC_DRIVER;
        }
    }

    public static function getStorageProviderName($storageDriver){
        if($storageDriver)
            if($storageDriver == Attachment::PUBLIC_DRIVER){
                return 'public';
            }
        if($storageDriver == Attachment::S3_DRIVER){
            return 's3';
        }
        if($storageDriver == Attachment::WAS_DRIVER){
            return 'wasabi';
        }
        if($storageDriver == Attachment::DO_DRIVER){
            return 'do_spaces';
        }
        if($storageDriver == Attachment::MINIO_DRIVER){
            return 'minio';
        }
        if($storageDriver == Attachment::PUSHR_DRIVER){
            return 'pushr';
        }
        else{
            return 'public';
        }
    }

    /**
     * Copies file from pushr to local, then copies the files on pushr again
     * Pushrcdn can't do $storage->copy due to failing AWSS3Adapter::getRawVisibility
     * @param $attachment
     * @param $newFileName
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function pushrCDNCopy($attachment, $newFileName){
        $storage = Storage::disk(AttachmentServiceProvider::getStorageProviderName($attachment->driver));
        // Pushr logic - Copy alternative as S3Adapter fails to do ->copy operations
        $remoteFile = $storage->get($attachment->filename);
        $localStorage = Storage::disk('public');
        $tmpFile = "tmp/".$attachment->id . '.' . $attachment->type;
        $localStorage->put($tmpFile, $remoteFile);
        $storage->put($newFileName, $localStorage->get($tmpFile), 'public');
        $localStorage->delete($tmpFile);
    }

}
