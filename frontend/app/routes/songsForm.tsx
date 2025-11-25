import React, { useState } from "react";
import axios from "axios";

const SongsForm: React.FC = () => {
  const [title, setTitle] = useState("");
  const [artist, setArtist] = useState("");
  const [description, setDescription] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [message, setMessage] = useState("");

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!file) {
      setMessage("Please select a song file.");
      return;
    }

    const formData = new FormData();
    formData.append("title", title);
    formData.append("artist", artist);
    formData.append("description", description);
    formData.append("file", file);

    try {
      const response = await axios.post("http://127.0.0.1:8000/api/songs", formData, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      });

      setMessage(`✅ Song uploaded: ${response.data.title}`);
      setTitle("");
      setArtist("");
      setDescription("");
      setFile(null);
    } catch (error: any) {
      console.error(error);
      setMessage("❌ Upload failed. Please check backend CORS or validation errors.");
    }
  };

  return (
    <div className="max-w-lg mx-auto mt-10 bg-white shadow-xl p-6 rounded-2xl">
      <h2 className="text-2xl font-bold mb-6 text-center text-black">🎵 Upload a New Song</h2>

      <form onSubmit={handleSubmit} className="space-y-4">
        <div>
          <label className="block mb-1 font-medium text-gray-400 text-black">Title</label>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            className="w-full border text-black border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring focus:ring-blue-200 text-black"
            required
          />
        </div>

        <div>
          <label className="block mb-1 font-medium text-gray-400">Artist</label>
          <input
            type="text"
            value={artist}
            onChange={(e) => setArtist(e.target.value)}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring focus:ring-blue-200 text-black"
            required
          />
        </div>

        <div>
          <label className="block mb-1 font-medium text-gray-400">Description</label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring focus:ring-blue-200 text-black"
            rows={3}
          />
        </div>

        <div>
          <label className="block mb-1 font-medium text-gray-400">Upload File (MP3/WAV)</label>
          <input
            type="file"
            accept=".mp3,.wav"
            onChange={(e) => setFile(e.target.files?.[0] || null)}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-black"
            required
          />
        </div>

        <button
          type="submit"
          className="w-full bg-blue-600 text-white py-2 rounded-lg font-semibold hover:bg-blue-700 transition"
        >
          Upload Song
        </button>
      </form>

      {message && <p className="mt-4 text-center text-gray-700">{message}</p>}
    </div>
  );
};

export default SongsForm;
