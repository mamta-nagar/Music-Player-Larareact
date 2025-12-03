import React, { useState, useEffect } from 'react';
import { Music, Play, Pause, SkipBack, SkipForward, Volume2, Heart, Search, Home, Library, Plus } from 'lucide-react';

interface Song {
  id: number;
  title: string;
  artist: string;
  file_path: string;
  description?: string;
  duration?: string;
  cover_image?: string;
}

export default function SpotifyLandingPage() {
  const [songs, setSongs] = useState<Song[]>([]);
  const [currentSong, setCurrentSong] = useState<Song | null>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [audioElement, setAudioElement] = useState<HTMLAudioElement | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);

  // Upload form state
  const [title, setTitle] = useState('');
  const [artist, setArtist] = useState('');
  const [description, setDescription] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [uploadMessage, setUploadMessage] = useState('');

  useEffect(() => {
    fetchSongs();
    const audio = new Audio();
    setAudioElement(audio);

    audio.addEventListener('timeupdate', () => {
      setCurrentTime(audio.currentTime);
    });

    audio.addEventListener('loadedmetadata', () => {
      setDuration(audio.duration);
    });

    audio.addEventListener('ended', () => {
      setIsPlaying(false);
      handleNext();
    });

    return () => {
      audio.pause();
      audio.src = '';
    };
  }, []);

  const fetchSongs = async () => {
    try {
      const response = await fetch('http://127.0.0.1:8000/api/songs');
      const data = await response.json();
      setSongs(data);
    } catch (error) {
      console.error('Error fetching songs:', error);
    }
  };

  const playSong = (song: Song) => {
    if (audioElement) {
      if (currentSong?.id === song.id) {
        if (isPlaying) {
          audioElement.pause();
          setIsPlaying(false);
        } else {
          audioElement.play();
          setIsPlaying(true);
        }
      } else {
        audioElement.src = song.file_path;
        audioElement.play();
        setCurrentSong(song);
        setIsPlaying(true);
      }
    }
  };

  const handleNext = () => {
    if (currentSong && songs.length > 0) {
      const currentIndex = songs.findIndex(s => s.id === currentSong.id);
      const nextIndex = (currentIndex + 1) % songs.length;
      playSong(songs[nextIndex]);
    }
  };

  const handlePrevious = () => {
    if (currentSong && songs.length > 0) {
      const currentIndex = songs.findIndex(s => s.id === currentSong.id);
      const prevIndex = currentIndex === 0 ? songs.length - 1 : currentIndex - 1;
      playSong(songs[prevIndex]);
    }
  };

  const handleSeek = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newTime = parseFloat(e.target.value);
    if (audioElement) {
      audioElement.currentTime = newTime;
      setCurrentTime(newTime);
    }
  };

  const formatTime = (time: number) => {
    const minutes = Math.floor(time / 60);
    const seconds = Math.floor(time % 60);
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
  };

  const handleUpload = async (e: React.MouseEvent) => {
    e.preventDefault();
    if (!file) {
      setUploadMessage('Please select a file');
      return;
    }

    const formData = new FormData();
    formData.append('title', title);
    formData.append('artist', artist);
    formData.append('description', description);
    formData.append('file_path', file);

    try {
      const response = await fetch('http://127.0.0.1:8000/api/songs', {
        method: 'POST',
        body: formData,
        
      });

      if (response.ok) {
        setUploadMessage('✅ Song uploaded successfully!');
        setTitle('');
        setArtist('');
        setDescription('');
        setFile(null);
        fetchSongs();
        setTimeout(() => {
          setShowUploadModal(false);
          setUploadMessage('');
        }, 2000);
      }
    } catch (error) {
      setUploadMessage('❌ Upload failed');
    }
  };

  const filteredSongs = songs.filter(song =>
    song.title.toLowerCase().includes(searchQuery.toLowerCase()) ||
    song.artist.toLowerCase().includes(searchQuery.toLowerCase())
  );

  

  return (
    <div className="flex h-screen bg-black text-white">
      {/* Sidebar */}
      <aside className="w-64 bg-black border-r border-gray-800 p-6 flex flex-col">
        <div className="flex items-center gap-2 mb-8">
          <Music className="w-8 h-8 text-green-500" />
          <h1 className="text-xl font-bold">Cloud Music</h1>
        </div>

        <nav className="space-y-4 flex-1">
          <button className="flex items-center gap-3 w-full px-4 py-3 rounded-lg bg-gray-900 hover:bg-gray-800 transition">
            <Home className="w-5 h-5" />
            <span className="font-medium">Home</span>
          </button>

          <button className="flex items-center gap-3 w-full px-4 py-3 rounded-lg hover:bg-gray-900 transition">
            <Search className="w-5 h-5" />
            <span className="font-medium">Search</span>
          </button>

          <button className="flex items-center gap-3 w-full px-4 py-3 rounded-lg hover:bg-gray-900 transition">
            <Library className="w-5 h-5" />
            <span className="font-medium">Your Library</span>
          </button>

          <button
            onClick={() => setShowUploadModal(true)}
            className="flex items-center gap-3 w-full px-4 py-3 rounded-lg bg-green-600 hover:bg-green-700 transition mt-6"
          >
            <Plus className="w-5 h-5" />
            <span className="font-medium">Upload Song</span>
          </button>
        </nav>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col overflow-hidden">
        {/* Header with Search */}
        <header className="bg-gradient-to-b from-gray-900 to-black p-6">
          <div className="max-w-md">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
              <input
                type="text"
                placeholder="Search songs or artists..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="w-full pl-10 pr-4 py-3 bg-white/10 rounded-full text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500"
              />
            </div>
          </div>
        </header>

        {/* Songs Grid */}
        <div className="flex-1 overflow-y-auto p-6">
          <h2 className="text-3xl font-bold mb-6">Your Songs</h2>
          
          {filteredSongs.length === 0 ? (
            <div className="text-center py-12">
              <Music className="w-16 h-16 mx-auto mb-4 text-gray-600" />
              <p className="text-gray-400 text-lg">No songs found</p>
              <button
                onClick={() => setShowUploadModal(true)}
                className="mt-4 px-6 py-2 bg-green-600 rounded-full hover:bg-green-700 transition"
              >
                Upload Your First Song
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
              {filteredSongs.map((song) => (
                <div
                  key={song.id}
                  className="bg-gray-900 p-4 rounded-lg hover:bg-gray-800 transition cursor-pointer group"
                  onClick={() => playSong(song)}
                >
                  <div className="relative mb-4">
                    <div className="aspect-square bg-gradient-to-br from-green-600 to-blue-600 rounded-lg flex items-center justify-center">
                      <Music className="w-12 h-12 text-white/80" />
                    </div>
                    <button
                      className="absolute bottom-2 right-2 w-12 h-12 bg-green-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transform translate-y-2 group-hover:translate-y-0 transition shadow-lg"
                      onClick={(e) => {
                        e.stopPropagation();
                        playSong(song);
                      }}
                    >
                      {currentSong?.id === song.id && isPlaying ? (
                        <Pause className="w-6 h-6 text-black" fill="black" />
                      ) : (
                        <Play className="w-6 h-6 text-black ml-1" fill="black" />
                      )}
                    </button>
                  </div>
                  <h3 className="font-semibold truncate mb-1">{song.title}</h3>
                  <p className="text-sm text-gray-400 truncate">{song.artist}</p>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Now Playing Bar */}
        {currentSong && (
          <footer className="bg-gray-900 border-t border-gray-800 p-4">
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center gap-4 flex-1">
                <div className="w-14 h-14 bg-gradient-to-br from-green-600 to-blue-600 rounded flex items-center justify-center flex-shrink-0">
                  <Music className="w-6 h-6 text-white" />
                </div>
                <div className="min-w-0">
                  <h4 className="font-semibold truncate">{currentSong.title}</h4>
                  <p className="text-sm text-gray-400 truncate">{currentSong.artist}</p>
                </div>
                <button className="ml-2 hover:text-green-500 transition">
                  <Heart className="w-5 h-5" />
                </button>
              </div>

              <div className="flex flex-col items-center gap-2 flex-1">
                <div className="flex items-center gap-4">
                  <button onClick={handlePrevious} className="hover:text-white transition text-gray-400">
                    <SkipBack className="w-5 h-5" />
                  </button>
                  <button
                    onClick={() => playSong(currentSong)}
                    className="w-10 h-10 bg-white rounded-full flex items-center justify-center hover:scale-105 transition"
                  >
                    {isPlaying ? (
                      <Pause className="w-5 h-5 text-black" fill="black" />
                    ) : (
                      <Play className="w-5 h-5 text-black ml-0.5" fill="black" />
                    )}
                  </button>
                  <button onClick={handleNext} className="hover:text-white transition text-gray-400">
                    <SkipForward className="w-5 h-5" />
                  </button>
                </div>

                <div className="flex items-center gap-2 w-full max-w-md">
                  <span className="text-xs text-gray-400 w-10 text-right">{formatTime(currentTime)}</span>
                  <input
                    type="range"
                    min="0"
                    max={duration || 0}
                    value={currentTime}
                    onChange={handleSeek}
                    className="flex-1 h-1 bg-gray-600 rounded-full appearance-none cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-3 [&::-webkit-slider-thumb]:h-3 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white"
                  />
                  <span className="text-xs text-gray-400 w-10">{formatTime(duration)}</span>
                </div>
              </div>

              <div className="flex items-center gap-2 flex-1 justify-end">
                <Volume2 className="w-5 h-5" />
                <input
                  type="range"
                  min="0"
                  max="100"
                  defaultValue="70"
                  onChange={(e) => audioElement && (audioElement.volume = parseInt(e.target.value) / 100)}
                  className="w-24 h-1 bg-gray-600 rounded-full appearance-none cursor-pointer [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-3 [&::-webkit-slider-thumb]:h-3 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-white"
                />
              </div>
            </div>
          </footer>
        )}
      </main>

      {/* Upload Modal */}
      {showUploadModal && (
        <div className="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4">
          <div className="bg-gray-900 rounded-lg p-6 max-w-md w-full">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-xl font-bold">Upload New Song</h3>
              <button
                onClick={() => setShowUploadModal(false)}
                className="text-gray-400 hover:text-white"
              >
                ✕
              </button>
            </div>

            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-2">Title</label>
                <input
                  type="text"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  className="w-full px-3 py-2 bg-gray-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Artist</label>
                <input
                  type="text"
                  value={artist}
                  onChange={(e) => setArtist(e.target.value)}
                  className="w-full px-3 py-2 bg-gray-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Description</label>
                <textarea
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  className="w-full px-3 py-2 bg-gray-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                  rows={3}
                />
              </div>

              <div>
                <label className="block text-sm font-medium mb-2">Audio File (MP3/WAV)</label>
                <input
                  type="file"
                  accept=".mp3,.wav"
                  onChange={(e) => setFile(e.target.files?.[0] || null)}
                  className="w-full px-3 py-2 bg-gray-800 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                  required
                />
              </div>

              <button
                onClick={handleUpload}
                className="w-full py-3 bg-green-600 rounded-lg font-semibold hover:bg-green-700 transition"
              >
                Upload Song
              </button>

              {uploadMessage && (
                <p className="text-center text-sm">{uploadMessage}</p>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}