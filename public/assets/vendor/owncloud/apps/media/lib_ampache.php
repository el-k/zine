<?php

/**
* ownCloud - media plugin
*
* @author Robin Appelman
* @copyright 2010 Robin Appelman icewind1991@gmail.com
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

//implementation of ampache's xml api
class OC_MEDIA_AMPACHE{
	
	/**
	* do the initial handshake
	* @param array params
	*/
	public static function handshake($params){
		$auth=(isset($params['auth']))?$params['auth']:false;
		$user=(isset($params['user']))?$params['user']:false;
		$time=(isset($params['timestamp']))?$params['timestamp']:false;
		$now=time();
		if($now-$time>(10*60)){
			echo("<root>
	<error code='400'>timestamp is more then 10 minutes old</error>
</root>");
		}
		if($auth and $user and $time){
			$query=OC_DB::prepare("SELECT user_id, user_password_sha256 from *PREFIX*media_users WHERE user_id=?");
			$users=$query->execute(array($user))->fetchAll();
			if(count($users)>0){
				$pass=$users[0]['user_password_sha256'];
				$key=hash('sha256',$time.$pass);
				if($key==$auth){
					$token=hash('sha256','oc_media_'.$key);
					OC_MEDIA_COLLECTION::$uid=$users[0]['user_id'];
					$date=date('c');//todo proper update/add/clean dates
					$songs=OC_MEDIA_COLLECTION::getSongCount();
					$artists=OC_MEDIA_COLLECTION::getArtistCount();
					$albums=OC_MEDIA_COLLECTION::getAlbumCount();
					$query=OC_DB::prepare("INSERT INTO *PREFIX*media_sessions (`session_id`, `token`, `user_id`, `start`) VALUES (NULL, ?, ?, now());");
					$query->execute(array($token,$user));
					$expire=date('c',time()+600);
					echo("<root>
	<auth>$token</auth>
	<version>350001</version>
	<update>$date</update>
	<add>$date</add>
	<clean>$date</clean>
	<songs>$songs</songs>
	<artists>$artists</artists>
	<albums>$albums</albums>\
	<session_length>600</session_length>
	<session_expire>$expire</session_expire>
	<tags>0</tags>
	<videos>0</videos>
</root>");
					return;
				}
			}
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
		}else{
			echo("<root>
	<error code='400'>Missing arguments</error>
</root>");
		}
	}
	
	public static function ping($params){
		if(isset($params['auth'])){
			if(self::checkAuth($params['auth'])){
				self::updateAuth($params['auth']);
			}else{
				echo("<root>
	<error code='400'>Invalid login</error>
</root>");
				return;
			}
		}
		echo('<root>');
		echo('<version>350001</version>');
		echo('</root>');
	}
	
	public static function checkAuth($auth){
		if(is_array($auth)){
			if(isset($auth['auth'])){
				$auth=$auth['auth'];
			}else{
				return false;
			}
		}
		//remove old sessions
		$query=OC_DB::prepare("DELETE from *PREFIX*media_sessions WHERE start<(NOW()-600)");
		$query->execute();
		
		$query=OC_DB::prepare("SELECT user_id from *PREFIX*media_sessions WHERE token=?");
		$users=$query->execute(array($auth))->fetchAll();
		if(count($users)>0){
			OC_MEDIA_COLLECTION::$uid=$users[0]['user_id'];
			return $users[0]['user_id'];
		}else{
			return false;
		}
	}
	
	public static function updateAuth($auth){
		$query=OC_DB::prepare("UPDATE *PREFIX*media_sessions SET start=CURRENT_TIMESTAMP WHERE token=?");
		$query->execute(array($auth));
	}
	
	private static function printArtist($artist){
		$albums=count(OC_MEDIA_COLLECTION::getAlbums($artist['artist_id']));
		$songs=count(OC_MEDIA_COLLECTION::getSongs($artist['artist_id']));
		$id=$artist['artist_id'];
		$name=htmlentities($artist['artist_name'], ENT_COMPAT, 'UTF-8');
		echo("\t<artist id='$id'>\n");
		echo("\t\t<name>$name</name>\n");
		echo("\t\t<albums>$albums</albums>\n");
		echo("\t\t<songs>$songs</songs>\n");
		echo("\t\t<rating>0</rating>\n");
		echo("\t\t<preciserating>0</preciserating>\n");
		echo("\t</artist>\n");
	}
	
	private static function printAlbum($album,$artistName=false){
		if(!$artistName){
			$artistName=OC_MEDIA_COLLECTION::getArtistName($album['album_artist']);
		}
		$artistName=htmlentities($artistName, ENT_COMPAT, 'UTF-8');
		$songs=count(OC_MEDIA_COLLECTION::getSongs($album['album_artist'],$album['album_id']));
		$id=$album['album_id'];
		$name=htmlentities($album['album_name'], ENT_COMPAT, 'UTF-8');
		$artist=$album['album_artist'];
		echo("\t<album id='$id'>\n");
		echo("\t\t<name>$name</name>\n");
		echo("\t\t<artist id='$artist'>$artistName</artist>\n");
		echo("\t\t<tracks>$songs</tracks>\n");
		echo("\t\t<rating>0</rating>\n");
		echo("\t\t<preciserating>0</preciserating>\n");
		echo("\t</album>\n");
	}
	
	private static function printSong($song,$artistName=false,$albumName=false){
		if(!$artistName){
			$artistName=OC_MEDIA_COLLECTION::getArtistName($song['song_artist']);
		}
		if(!$albumName){
			$albumName=OC_MEDIA_COLLECTION::getAlbumName($song['song_album']);
		}
		$artistName=htmlentities($artistName, ENT_COMPAT, 'UTF-8');
		$albumName=htmlentities($albumName, ENT_COMPAT, 'UTF-8');
		$id=$song['song_id'];
		$name=htmlentities($song['song_name'], ENT_COMPAT, 'UTF-8');
		$artist=$song['song_artist'];
		$album=$song['song_album'];
		echo("\t<song id='$id'>\n");
		echo("\t\t<title>$name</title>\n");
		echo("\t\t<artist id='$artist'>$artistName</artist>\n");
		echo("\t\t<album id='$album'>$albumName</album>\n");
		$url=OC_Helper::linkTo('media', 'server/xml.server.php', null, true)."?action=play&song=$id&auth={$_GET['auth']}";
		$url=htmlentities($url);
		echo("\t\t<url>$url</url>\n");
		echo("\t\t<time>{$song['song_length']}</time>\n");
		echo("\t\t<track>{$song['song_track']}</track>\n");
		echo("\t\t<size>{$song['song_size']}</size>\n");
		echo("\t\t<art></art>\n");
		echo("\t\t<rating>0</rating>\n");
		echo("\t\t<preciserating>0</preciserating>\n");
		echo("\t</song>\n");
	}
	
	public static function artists($params){
		if(!self::checkAuth($params)){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		$filter=isset($params['filter'])?$params['filter']:'';
		$exact=isset($params['exact'])?($params['exact']=='true'):false;
		$artists=OC_MEDIA_COLLECTION::getArtists($filter,$exact);
		echo('<root>');
		foreach($artists as $artist){
			self::printArtist($artist);
		}
		echo('</root>');
	}
	
	public static function artist_songs($params){
		if(!self::checkAuth($params)){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		$filter=isset($params['filter'])?$params['filter']:'';
		$songs=OC_MEDIA_COLLECTION::getSongs($filter);
		$artist=OC_MEDIA_COLLECTION::getArtistName($filter);
		echo('<root>');
		foreach($songs as $song){
			self::printSong($song,$artist);
		}
		echo('</root>');
	}
	
	public static function artist_albums($params){
		if(!self::checkAuth($params)){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		global $SITEROOT;
		$filter=$params['filter'];
		$albums=OC_MEDIA_COLLECTION::getAlbums($filter);
		$artist=OC_MEDIA_COLLECTION::getArtistName($filter);
		echo('<root>');
		foreach($albums as $album){
			self::printAlbum($album,$artist);
		}
		echo('</root>');
	}
	
	public static function albums($params){
		if(!self::checkAuth($params)){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		$filter=isset($params['filter'])?$params['filter']:'';
		$exact=isset($params['exact'])?($params['exact']=='true'):false;
		$albums=OC_MEDIA_COLLECTION::getAlbums(0,$filter,$exact);
		echo('<root>');
		foreach($albums as $album){
			self::printAlbum($album,$artist);
		}
		echo('</root>');
	}
	
	public static function album_songs($params){
		if(!self::checkAuth($params)){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		$songs=OC_MEDIA_COLLECTION::getSongs(0,$params['filter']);
		if(count($songs)>0){
			$artist=OC_MEDIA_COLLECTION::getArtistName($songs[0]['song_artist']);
		}
		echo('<root>');
		foreach($songs as $song){
			self::printSong($song,$artist);
		}
		echo('</root>');
	}
	
	public static function songs($params){
		if(!self::checkAuth($params)){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		$filter=isset($params['filter'])?$params['filter']:'';
		$exact=isset($params['exact'])?($params['exact']=='true'):false;
		$songs=OC_MEDIA_COLLECTION::getSongs(0,0,$filter,$exact);
		echo('<root>');
		foreach($songs as $song){
			self::printSong($song);
		}
		echo('</root>');
	}
	
	public static function song($params){
		if(!self::checkAuth($params)){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		if($song=OC_MEDIA_COLLECTION::getSong($params['filter'])){
			echo('<root>');
			self::printSong($song);
			echo('</root>');
		}
	}
	
	public static function play($params){
		$username=!self::checkAuth($params);
		if($username){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		if($song=OC_MEDIA_COLLECTION::getSong($params['song'])){
			OC_Util::setupFS($song["song_user"]);

			header('Content-type: '.OC_Filesystem::getMimeType($song['song_path']));
			header('Content-Length: '.$song['song_size']);
			OC_Filesystem::readfile($song['song_path']);
		}
	}
	
	public static function url_to_song($params){
		if(!self::checkAuth($params)){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		$url=$params['url'];
		$songId=substr($url,strrpos($url,'song=')+5);
		if($song=OC_MEDIA_COLLECTION::getSong($songId)){
			echo('<root>');
			self::printSong($song);
			echo('</root>');
		}
	}
	
	public static function search_songs($params){
		if(!self::checkAuth($params)){
			echo("<root>
	<error code='400'>Invalid login</error>
</root>");
			return;
		}
		$filter=$params['filter'];
		$artists=OC_MEDIA_COLLECTION::getArtists($filter);
		$albums=OC_MEDIA_COLLECTION::getAlbums(0,$filter);
		$songs=OC_MEDIA_COLLECTION::getSongs(0,0,$filter);
		foreach($artists as $artist){
			$songs=array_merge($songs,OC_MEDIA_COLLECTION::getSongs($artist['artist_id']));
		}
		foreach($albums as $album){
			$songs=array_merge($songs,OC_MEDIA_COLLECTION::getSongs($album['album_artist'],$album['album_id']));
		}
		echo('<root>');
		foreach($songs as $song){
			self::printSong($song);
		}
		echo('</root>');
	}
}

?>
