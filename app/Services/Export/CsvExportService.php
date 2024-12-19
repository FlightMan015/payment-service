<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Services\FileGenerator\FileGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\AbstractCsv;
use League\Csv\Exception;

class CsvExportService implements ExportService
{
    /**
     * CsvExportService Constructor
     *
     * @param FileGenerator $fileGenerator
     */
    public function __construct(private readonly FileGenerator $fileGenerator)
    {
    }

    /**
     * The method exports data to an S3.
     *
     * An array of data is converted to CSV format, split into chunks
     * and then each chunk is passed to an S3 storage disk
     * (the file name remains the same for all chunks).
     *
     * @param array $data
     * @param string $fileName
     * @param int|null $sizeLimit
     *
     * @throws Exception
     *
     * @return bool
     */
    public function exportToS3(array $data, string $fileName, int|null $sizeLimit = null): bool
    {
        /** @var AbstractCsv $file */
        $file = $this->fileGenerator->generateFile(data: $data);
        $fileSize = strlen($file->toString());

        if (is_null($sizeLimit) || $fileSize <= $sizeLimit) {
            Storage::disk(name: 's3')->put(path: $fileName, contents: $file->toString());
            $this->infoLogThatFileWasUploaded(fileName: $fileName, fileSize: $fileSize);

            return true;
        }

        foreach ($this->splitFileIntoChunks(file: $file, sizeLimit: $sizeLimit) as $index => $csvChunk) {
            $chunkFileName = $this->buildIndexedFileName($fileName, $index);
            Storage::disk(name: 's3')->put(path: $chunkFileName, contents: $csvChunk);
            $this->infoLogThatFileWasUploaded(fileName: $chunkFileName, fileSize: strlen($csvChunk));
        }

        return true;
    }

    private function buildIndexedFileName(string $fileName, int $index): string
    {
        $extension = pathinfo(path: $fileName, flags: PATHINFO_EXTENSION);
        $baseName = basename(path: $fileName);
        $fileNameWithoutExtension = basename(path: $baseName, suffix: '.' . $extension);
        $directoryName = dirname(path: $fileName);

        $processedFileName = sprintf('%s_%d.%s', $fileNameWithoutExtension, $index + 1, $extension);

        // Combine the directory back with the processed file name
        return rtrim(string: $directoryName, characters: DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $processedFileName;
    }

    /**
     * The method splits data into chunks ready for transmission.
     *
     * It breaks the CSV string representation of the file into chunks,
     * ensuring that each chunk doesn't exceed the maximum size limit.
     * It also ensures that CSV rows aren't split across chunks.
     *
     * @param AbstractCsv $file
     * @param int $sizeLimit
     *
     * @throws Exception
     */
    private function splitFileIntoChunks(AbstractCsv $file, int $sizeLimit): array
    {
        $csvString = $file->toString();

        // Break the string into chunks
        $chunks = str_split(string: $csvString, length: $sizeLimit);
        $remainder = '';

        foreach ($chunks as &$chunk) {
            $chunk = $remainder . $chunk;
            $remainder = '';

            // Determine if a row was split across chunks
            $lastNewLinePos = strrpos(haystack: $chunk, needle: "\n");
            $isNotLastCharacter = $lastNewLinePos !== false && $lastNewLinePos !== strlen($chunk) - 1;

            // If row is split, correct chunk and add the split row's part in the remainder
            if ($isNotLastCharacter) {
                $remainder = substr(string: $chunk, offset: $lastNewLinePos + 1);
                $chunk = substr(string: $chunk, offset: 0, length: $lastNewLinePos + 1);
            }

            // remove new line character from the end of the chunk
            $chunk = rtrim(string: $chunk, characters: PHP_EOL);
        }

        // This variable must be unset just after foreach to prevent possible side-effects.
        unset($chunk);

        // Add any remaining bit after the splitting process
        if (!empty($remainder)) {
            $lastChunk = last($chunks);

            end(array: $chunks);
            $chunks[key($chunks)] = $lastChunk . PHP_EOL . $remainder;
            reset(array: $chunks);
        }

        return $chunks;
    }

    private function infoLogThatFileWasUploaded(string $fileName, int $fileSize): void
    {
        Log::info(message: __('messages.export.file_uploaded_to_s3'), context: [
            'file_name' => $fileName,
            'file_size_in_bytes' => $fileSize
        ]);
    }
}
