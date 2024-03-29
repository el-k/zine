Collection={
	artists:[],
	albums:[],
	songs:[],
	artistsById:{},
	albumsById:{},
	loaded:false,
	loading:false,
	loadedListeners:[],
	load:function(ready){
		if(ready){
			Collection.loadedListeners.push(ready);
		}
		if(!Collection.loading){
			Collection.loading=true;
			$.ajax({
				url: OC.linkTo('media','ajax/api.php')+'?action=get_collection',
				dataType: 'json',
				success: function(data){
					//normalize the data
					for(var i=0;i<data.artists.length;i++){
						var artist=data.artists[i];
						var artistData={name:artist.artist_name,songs:[],albums:[]};
						Collection.artistsById[artist.artist_id]=artistData;
						Collection.artists.push(artistData);
					}
					for(var i=0;i<data.albums.length;i++){
						var album=data.albums[i];
						if(Collection.artistsById[album.album_artist]){
							var artistName=Collection.artistsById[album.album_artist].name;
						}else{
							var artistName='unknown';
						}
						var albumData={name:album.album_name,artist:artistName,songs:[]};
						Collection.albumsById[album.album_id]=albumData;
						Collection.albums.push(albumData);
						if(Collection.artistsById[album.album_artist]){
							Collection.artistsById[album.album_artist].albums.push(albumData);
						}
					}
					for(var i=0;i<data.songs.length;i++){
						var song=data.songs[i];
						if(Collection.artistsById[song.song_artist] && Collection.albumsById[song.song_album]){
							var songData={
								name:song.song_name,
								artist:Collection.artistsById[song.song_artist].name,
								album:Collection.albumsById[song.song_album].name,
								lastPlayed:song.song_lastplayed,
								length:song.song_length,
								path:song.song_path,
								playCount:song.song_playcount,
							};
							Collection.songs.push(songData);
							Collection.artistsById[song.song_artist].songs.push(songData);
							Collection.albumsById[song.song_album].songs.push(songData);
						}
					}
					
					Collection.artists.sort(function(a,b){
						if(!a.name){
							return -1;
						}
						return a.name.localeCompare(b.name);
					});
					
					Collection.loaded=true;
					Collection.loading=false;
					for(var i=0;i<Collection.loadedListeners.length;i++){
						Collection.loadedListeners[i]();
					}
					if(data.songs.length==0){
						$('#scan input.start').val(t('media','Scan Collection'));
						$('#scan input.start').click();
					}
					
				}
			});
		}
	},
	display:function(){
		if(Collection.parent){
			Collection.parent.show();
		}
		if(!Collection.loaded){
			Collection.load(Collection.display)
		}else{
			if(Collection.parent){
				Collection.parent.find('tr:not(.template)').remove();
				var template=Collection.parent.find('tr.template');
				var lastArtist='';
				var lastAlbum='';
				$.each(Collection.artists,function(i,artist){
					if(artist.name && artist.songs.length>0){
						var tr=template.clone().removeClass('template');
						if(artist.songs.length>1){
							tr.find('td.title a').text(artist.songs.length+' '+t('media','songs'));
							tr.find('td.album a').text(artist.albums.length+' '+t('media','albums'));
						}else{
							tr.find('td.title a').text(artist.songs[0].name);
							tr.find('td.album a').text(artist.albums[0].name);
						}
						tr.find('td.artist a').text(artist.name);
						tr.data('artistData',artist);
						tr.find('td.artist a').click(function(event){
							event.preventDefault();
							PlayList.add(artist);
							PlayList.play(0);
							Collection.parent.find('tr').removeClass('active');
							$('tr[data-artist="'+artist.name+'"]').addClass('active');
						});
						if(artist.songs.length>1){
							var expander=$('<a class="expander">&gt;</a>');
							expander.data('expanded',false);
							expander.click(function(event){
								var tr=$(this).parent().parent();
								if(expander.data('expanded')){
									Collection.hideArtist(tr.data('artist'));
								}else{
									Collection.showArtist(tr.data('artist'));
								}
							});
						}
						tr.find('td.artist').addClass('buttons');
						Collection.addButtons(tr,artist);
						tr.children('td.artist').append(expander);
						tr.attr('data-artist',artist.name);
						Collection.parent.find('tbody').append(tr);
					}
				});
			}
		}
	},
	showArtist:function(artist){
		var tr=Collection.parent.find('tr[data-artist="'+artist+'"]');
		var lastRow=tr;
		var artist=tr.data('artistData');
		var first=true;
		$.each(artist.albums,function(j,album){
			$.each(album.songs,function(i,song){
				if(first){
					newRow=tr;
				}else{
					var newRow=tr.clone();
					newRow.find('td.artist').text('');
					newRow.find('.expander').remove();
				}
				newRow.find('td.album .expander').remove();
				if(i==0){
					newRow.find('td.album a').text(album.name);
					newRow.find('td.album a').click(function(event){
						event.preventDefault();
						PlayList.add(album);
						PlayList.play(0);
						Collection.parent.find('tr').removeClass('active');
						$('tr[data-album="'+album.name+'"]').addClass('active');
					});
					if(album.songs.length>1){
						var expander=$('<a class="expander">v </a>');
						expander.data('expanded',true);
						expander.click(function(event){
							var tr=$(this).parent().parent();
							if(expander.data('expanded')) {
								Collection.hideAlbum(tr.data('artist'),tr.data('album'));
							} else {
								Collection.showAlbum(tr.data('artist'),tr.data('album'));
							}
						});
						newRow.children('td.album').append(expander);
					}
					Collection.addButtons(newRow,album);
				} else {
					newRow.find('td.album a').text('');
					Collection.addButtons(newRow,song);
				}
				newRow.find('td.title a').text(song.name);
				newRow.find('td.title a').click(function(event){
					event.preventDefault();
					PlayList.add(song);
					PlayList.play(0);
					Collection.parent.find('tr').removeClass('active');
					$('tr[data-title="'+song.name+'"]').addClass('active');
				});
				newRow.attr('data-album',album.name);
				newRow.attr('data-title',song.name);
				newRow.attr('data-artist',artist.name);
				newRow.data('albumData',album);
				if(!first){
					lastRow.after(newRow);
				}
				first=false;
				lastRow=newRow;
			});
		});
		tr.removeClass('collapsed');
		tr.find('td.artist a.expander').data('expanded',true);
		tr.find('td.artist a.expander').addClass('expanded');
		tr.find('td.artist a.expander').text('v');
	},
	hideArtist:function(artist){
		var tr=Collection.parent.find('tr[data-artist="'+artist+'"]');
		var artist=tr.first().data('artistData');
		tr.first().find('td.album a').first().text(artist.albums.length+' '+t('media','albums'));
		tr.first().find('td.album a.expander').remove();
		tr.first().find('td.title a').text(artist.songs.length+' '+t('media','songs'));
		tr.first().find('td.album a').unbind('click');
		tr.first().find('td.title a').unbind('click');
		tr.each(function(i,row){
			if(i>0){
				$(row).remove();
			}
		});
		tr.find('td.artist a.expander').data('expanded',false);
		tr.find('td.artist a.expander').removeClass('expanded');
		tr.find('td.artist a.expander').text('>');
		Collection.addButtons(tr,artist);
	},
	showAlbum:function(artist,album){
		var tr = Collection.parent.find('tr[data-artist="'+artist+'"][data-album="'+album+'"]');
		var lastRow=tr;
		var albumData=tr.data('albumData');
		tr.find('td.album a.expander').data('expanded',true);
		tr.find('td.album a.expander').addClass('expanded');
		tr.find('td.album a.expander').text('v');
		$.each(albumData.songs,function(i,song){
			if(i>0){
				var newRow=tr.clone();
				newRow.find('a.expander').remove();
				newRow.find('td.album a').text('');
				newRow.find('td.artist a').text('');
			}else{
				var newRow=tr;
			}
			newRow.find('td.title a').text(song.name);
			newRow.find('td.title a').click(function(event){
				event.preventDefault();
				PlayList.add(song);
				PlayList.play(0);
				Collection.parent.find('tr').removeClass('active');
				$('tr[data-title="'+song.name+'"]').addClass('active');
			});
			if(i>0){
				lastRow.after(newRow);
			}
			lastRow=newRow;
		});
	},
	hideAlbum:function(artist,album){
		var tr = Collection.parent.find('tr[data-artist="'+artist+'"][data-album="'+album+'"]');
		var albumData=tr.data('albumData');
		tr.first().find('td.title a').text(albumData.songs.length+' '+t('media','songs'));
		tr.find('td.album a.expander').data('expanded',false);
		tr.find('td.album a.expander').removeClass('expanded');
		tr.find('td.album a.expander').text('> ');
		tr.each(function(i,row){
			if(i>0){
				$(row).remove();
			}
		});
	},
	parent:null,
	hide:function(){
		if(Collection.parent){
			Collection.parent.hide();
		}
	},
	registerPlay:function(item){
		if(item){
			var song=Collection.findSong(item.artist,item.album,item.name);
			song.song_playcount++;
		}
	},
	addButtons:function(parent,data){
		buttons = parent.find('.buttons');
		if(buttons.find('.add').length<=0) {
			buttons.prepend('<img class="add action" src="'+OC.imagePath('core','actions/play-add')+'" title="Add to playlist" />');
		}
		buttons.find('.add').unbind('click');
		buttons.find('.add').click(function(event){
			event.preventDefault();
			PlayList.add(data,true);
			PlayList.render();
		});
	},
	find:function(artistName,albumName,songName){
		if(songName){
			return Collection.findSong(artistName,albumName,songName);
		}else if(albumName){
			return Collection.findAlbum(artistName,albumName);
		}else{
			return Collection.findArtist(artistName);
		}
	},
	findArtist:function(name){
		for(var i=0;i<Collection.artists.length;i++){
			if(Collection.artists[i].name==name){
				return Collection.artists[i];
			}
		}
	},
	findAlbum:function(artistName,albumName){
		var artist=Collection.findArtist(artistName);
		if(artist){
			for(var i=0;i<artist.albums.length;i++){
				if(artist.albums[i].name==albumName){
					return artist.albums[i];
				}
			}
		}
	},
	findSong:function(artistName,albumName,songName){
		var album=Collection.findAlbum(artistName,albumName);
		if(album){
			for(var i=0;i<album.songs.length;i++){
				if(album.songs[i].name==songName){
					return album.songs[i];
				}
			}
		}
	},
	addSong:function(song){
		var artist=false
		var album=false;
		for(var i=0;i<Collection.artists.length;i++){
			if(Collection.artists[i].artist_id==song.song_artist){
				artist=Collection.artists[i];
				for(var j=0;j<artist.albums.length;j++){
					if(artist.albums[j].album_id==song.song_album){
						album=artist.albums[j];
						break;
					}
				}
				break;
			}
		}
		if(!artist){
			artist={artist_id:song.song_artist,artist_name:song.artist,albums:[]};
			Collection.artists.push(artist);
			if(!Collection.parent || Collection.parent.is(":visible")){
				Collection.display();
			}
			
		}
		if(!album){
			album={album_id:song.song_album,album_name:song.album,album_artist:song.song_artist,songs:[]};
			artist.albums.push(album)
		}
		album.songs.push(song)
	}
}

$(document).ready(function(){
	Collection.parent=$('#collection');
	Collection.load();
	Collection.parent.hide();
	$('#scan input.start').click(function(){
		$('#scan input.start').hide();
		$('#scan input.stop').show();
		$('#scan input.stop').click(function(){
			Scanner.toggle();
		});
		Scanner.scanCollection();
	});
});
