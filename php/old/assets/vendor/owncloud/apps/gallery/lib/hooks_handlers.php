<?php

/**
* ownCloud - gallery application
*
* @author Bartek Przybylski
* @copyright 2012 Bartek Przybylski bart.p.pl@gmail.com
* 
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either 
* version 3 of the License, or any later version.
* 
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*  
* You should have received a copy of the GNU Lesser General Public 
* License along with this library.  If not, see <http://www.gnu.org/licenses/>.
* 
*/

OC_Hook::connect("OC_Filesystem", "post_write", "OC_Gallery_Hooks_Handlers", "addPhotoFromPath");
OC_Hook::connect("OC_Filesystem", "delete", "OC_Gallery_Hooks_Handlers", "removePhoto");
OC_Hook::connect("OC_Filesystem", "post_rename", "OC_Gallery_Hooks_Handlers", "renamePhoto");

require_once(OC::$CLASSPATH['OC_Gallery_Album']);
require_once(OC::$CLASSPATH['OC_Gallery_Photo']);

class OC_Gallery_Hooks_Handlers {
  private static $APP_TAG = "Gallery";

  private static function isPhoto($filename) {
    OC_Log::write(self::$APP_TAG, "Checking file ".$filename." with mimetype ".OC_Filesystem::getMimeType($filename), OC_Log::DEBUG);
    if (substr(OC_Filesystem::getMimeType($filename), 0, 6) == "image/")
      return true;
    return false;
  }

  private static function directoryContainsPhotos($dirpath) {
    $dirhandle = opendir(OC::$CONFIG_DATADIRECTORY.$dirpath);
    if ($dirhandle != FALSE) {
      while (($filename = readdir($dirhandle)) != FALSE) {
        if ($filename[0] == '.') continue;
        if (self::isPhoto($dirpath.'/'.$filename)) return true;
      }
    }
    return false;
  }

  private static function createAlbum($path) {
    $new_album_name = trim(str_replace('/', '.', $path), '.');
    if ($new_album_name == '')
      $new_album_name = 'main';

    OC_Log::write(self::$APP_TAG, 'Creating new album '.$new_album_name, OC_Log::DEBUG);
    OC_Gallery_Album::create(OC_User::getUser(), $new_album_name, $path);

    return OC_Gallery_Album::find(OC_User::getUser(), null, $path);
  }

  public static function addPhotoFromPath($params) {
    if (!self::isPhoto($params['path'])) return;
    $fullpath = $params['path'];
    OC_Log::write(self::$APP_TAG, 'Adding file with path '. $fullpath, OC_Log::DEBUG);
    $path = substr($fullpath, 0, strrpos($fullpath, '/'));
    if ($path == '') $path = '/';
    $album = OC_Gallery_Album::find(OC_User::getUser(), null, $path);

    if ($album->numRows() == 0) {
      $album = self::createAlbum($path);
    }
    $album = $album->fetchRow();
    $albumId = $album['album_id'];
    $photo = OC_Gallery_Photo::find($albumId, $fullpath);
    if ($photo->numRows() == 0) { // don't duplicate photo entries
      OC_Log::write(self::$APP_TAG, 'Adding new photo to album', OC_Log::DEBUG);
      OC_Gallery_Photo::create($albumId, $fullpath);
    }

  }

  public static function removePhoto($params) {
    $path = $params['path'];
    if (OC_Filesystem::is_dir($path) && self::directoryContainsPhotos($path)) {
      OC_Gallery_Album::removeByPath($path, OC_User::getUser());
    } elseif (self::isPhoto($path)) {
      OC_Gallery_Photo::removeByPath($path);
    }
  }

  public static function renamePhoto($params) {
    if (OC_Filesystem::is_dir($params['newpath']) && self::directoryContainsPhotos($params['newpath'])) {
      OC_Gallery_Album::changePath($params['oldpath'], $params['newpath'], OC_User::getUser());
    } elseif (!self::isPhoto($params['newpath'])) {
      $olddir = substr($params['oldpath'], 0, strrpos($params['oldpath'], '/'));
      $newdir = substr($params['newpath'], 0, strrpos($params['newpath'], '/'));
      if ($olddir == '') $olddir = '/';
      if ($newdir == '') $newdir = '/';
      if (!self::isPhoto($params['newpath'])) return;
      OC_Log::write(self::$APP_TAG, 'Moving photo from '.$params['oldpath'].' to '.$params['newpath'], OC_Log::DEBUG);
      $album;
      $newAlbumId;
      $oldAlbumId;
      if ($olddir == $newdir) {
        // album changing is not needed
        $album = OC_Gallery_Album::find(OC_User::getUser(), null, $olddir);
        if ($album->numRows() == 0) {
          $album = self::createAlbum($newdir);
        }
        $album = $album->fetchRow();
        $newAlbumId = $oldAlbumId = $album['album_id'];
      } else {
        $newalbum = OC_Gallery_Album::find(OC_User::getUser(), null, $newdir);
        $oldalbum = OC_Gallery_Album::find(OC_User::getUser(), null, $olddir);

        if ($newalbum->numRows() == 0) {
          $newalbum = self::createAlbum($newdir);
        }
        $newalbum = $newalbum->fetchRow();
        if ($oldalbum->numRows() == 0) {
          OC_Gallery_Photo::create($newalbum['album_id'], $params['newpath']);
          return;
        }
        $oldalbum = $oldalbum->fetchRow();
        $newAlbumId = $newalbum['album_id'];
        $oldAlbumId = $oldalbum['album_id'];

      }
      OC_Gallery_Photo::changePath($oldAlbumId, $newAlbumId, $params['oldpath'], $params['newpath']);
    }
  }
}

?>
