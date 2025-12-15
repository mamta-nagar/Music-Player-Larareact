import { useState, useEffect, useRef } from 'react';
import echo from '../lib/echo';

interface Song {
  id: number;
  title: string;
  artist: string;
  file_path: string;
  description?: string;
  duration?: string;
  cover_image?: string;
}

interface PlaybackState {
  current_song_id: number | null;
  current_song: Song | null;
  current_time: number;
  is_playing: boolean;
  volume: number;
  active_device_id: string | null;
}

export const useMultiDevicePlayback = (token: string) => {
  const [sessionId, setSessionId] = useState<string>('');
  const [deviceId] = useState<string>(() => {
    let id = localStorage.getItem('device_id');
    if (!id) {
      id = `device_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
      localStorage.setItem('device_id', id);
    }
    return id;
  });

  const [playbackState, setPlaybackState] = useState<PlaybackState>({
    current_song_id: null,
    current_song: null,
    current_time: 0,
    is_playing: false,
    volume: 0.7,
    active_device_id: null,
  });

  const isUpdatingRef = useRef(false);
  const API_BASE_URL = 'http://127.0.0.1:8000/api';

  useEffect(() => {
    if (token) {
      initializeSession();
    }
  }, [token]);

  const initializeSession = async () => {
    try {
      const storedSessionId = localStorage.getItem('session_id');
      const url = storedSessionId 
        ? `${API_BASE_URL}/playback/session?session_id=${storedSessionId}`
        : `${API_BASE_URL}/playback/session`;
      
      const response = await fetch(url, {
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      });
      const data = await response.json();
      
      setSessionId(data.session_id);
      localStorage.setItem('session_id', data.session_id);
      setPlaybackState(data.playback_state);

      // Register device
      await fetch(`${API_BASE_URL}/playback/device/register`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
          session_id: data.session_id,
          device_id: deviceId,
          device_name: getDeviceName(),
          device_type: getDeviceType(),
        }),
      });

      setupWebSocket(data.session_id);
    } catch (error) {
      console.error('Failed to initialize session:', error);
    }
  };

  const setupWebSocket = (sessionId: string) => {
    console.log('🔌 Setting up WebSocket for session:', sessionId);
    
    echo.channel(`playback.${sessionId}`)
      .listen('.playback.updated', (event: any) => {
        console.log('📡 Received playback update:', event);

        if (event.playbackState.updated_by === deviceId) {
          console.log('⏭️ Skipping update from self');
          return;
        }

        console.log('✅ Applying playback update from another device');
        setPlaybackState(event.playbackState);
      });
  };

  const updatePlayback = async (updates: Partial<PlaybackState>) => {
    if (isUpdatingRef.current) return;
    
    isUpdatingRef.current = true;
    const newState = { ...playbackState, ...updates };
    setPlaybackState(newState);

    try {
      const response = await fetch(`${API_BASE_URL}/playback/update`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify({
          session_id: sessionId,
          device_id: deviceId,
          current_song_id: updates.current_song_id,
          current_time: updates.current_time,
          is_playing: updates.is_playing,
          volume: updates.volume,
        }),
      });

      if (!response.ok) {
        console.error('Failed to update playback');
      }
    } catch (error) {
      console.error('Failed to update playback:', error);
    } finally {
      setTimeout(() => {
        isUpdatingRef.current = false;
      }, 100);
    }
  };

  const getDeviceName = () => {
    const ua = navigator.userAgent;
    if (/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i.test(ua)) return 'Tablet';
    if (/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry/.test(ua)) return 'Mobile';
    return 'Desktop';
  };

  const getDeviceType = () => 'web';

  return {
    playbackState,
    updatePlayback,
    sessionId,
    deviceId,
  };
};