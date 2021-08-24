<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Extra helper methods for plagiarism_copyleaks component.
 * @package   plagiarism_copyleaks
 * @copyright 2021 Copyleaks
 * @author    Bayan Abuawad <bayana@copyleaks.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!function_exists('mime_content_type')) {
    /**
     * get mime type by file names
     * @param string $filename
     * @return string mime type
     */
    function mime_content_type($filename) {

        $mimetypes = array(
            'aac' => 'audio/aac',
            'abw' => 'application/x-abiword',
            'arc' => 'application/x-freearc',
            'avi' => 'video/x-msvideo',
            'azw' => 'application/vnd.amazon.ebook',
            'bin' => 'application/octet-stream',
            'bmp' => 'image/bmp',
            'bz' => 'application/x-bzip',
            'bz2' => 'application/x-bzip2',
            'csh' => 'application/x-csh',
            'css' => 'text/css',
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'eot' => 'application/vnd.ms-fontobject',
            'epub' => 'application/epub+zip',
            'gz' => 'application/gzip',
            'gif' => 'image/gif',
            'htm' => 'text/html',
            'html' => 'text/html',
            'ico' => 'image/vnd.microsoft.icon',
            'ics' => 'text/calendar',
            'jar' => 'application/java-archive',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'jsonld' => 'application/ld+json',
            'mid' => 'audio/midi audio/x-midi',
            'midi' => 'audio/midi audio/x-midi',
            'mjs' => 'text/javascript',
            'mp3' => 'audio/mpeg',
            'mpeg' => 'video/mpeg',
            'mpkg' => 'application/vnd.apple.installer+xml',
            'odp' => 'application/vnd.oasis.opendocument.presentation',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'oga' => 'audio/ogg',
            'ogv' => 'video/ogg',
            'ogx' => 'application/ogg',
            'opus' => 'audio/opus',
            'otf' => 'font/otf',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'php' => 'application/x-httpd-php',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'rar' => 'application/vnd.rar',
            'rtf' => 'application/rtf',
            'sh' => 'application/x-sh',
            'svg' => 'image/svg+xml',
            'swf' => 'application/x-shockwave-flash',
            'tar' => 'application/x-tar',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'ts' => 'video/mp2t',
            'ttf' => 'font/ttf',
            'txt' => 'text/plain',
            'vsd' => 'application/vnd.visio',
            'wav' => 'audio/wav',
            'weba' => 'audio/webm',
            'webm' => 'video/webm',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'xhtml' => 'application/xhtml+xml',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xml' => 'text/xml',
            'xul' => 'application/vnd.mozilla.xul+xml',
            'zip' => 'application/zip',
            '3gp' => 'video/3gpp',
            '3g2' => 'video/3gpp2',
            '7z' => 'application/x-7z-compressed'
        );

        $ext = strtolower(array_pop(explode('.', $filename)));
        if (array_key_exists($ext, $mimetypes)) {
            return $mimetypes[$ext];
        } else if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        } else {
            return 'application/octet-stream';
        }
    }
}
