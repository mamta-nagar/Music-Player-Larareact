import React, { useState, useEffect } from "react";
import type { Route } from "./+types/home";

import Login from "./login";
import Register from "./register";
import Songs from "./songs";

export function meta({}: Route.MetaArgs) {
  return [
    { title: "Cloud Music Player" },
    { name: "description", content: "Cloud Music Player Home" },
  ];
}

export default function Home() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [showLogin, setShowLogin] = useState(true);
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<any>(null);

  // Load saved token on refresh
  useEffect(() => {
    const savedToken = localStorage.getItem("token");
    const savedUser = localStorage.getItem("user");

    if (savedToken && savedUser) {
      setToken(savedToken);
      setUser(JSON.parse(savedUser));
      setIsAuthenticated(true);
    }
  }, []);

  // When login succeeds
  const handleLoginSuccess = (accessToken: string, userData: any) => {
    localStorage.setItem("token", accessToken);
    localStorage.setItem("user", JSON.stringify(userData));

    setToken(accessToken);
    setUser(userData);
    setIsAuthenticated(true);
  };

  // When register succeeds
  const handleRegisterSuccess = (accessToken: string, userData: any) => {
    localStorage.setItem("token", accessToken);
    localStorage.setItem("user", JSON.stringify(userData));

    setToken(accessToken);
    setUser(userData);
    setIsAuthenticated(true);
  };

  // Logout
  const handleLogout = async () => {
    if (token) {
      try {
        await fetch("http://127.0.0.1:8000/api/logout", {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
      } catch (err) {
        console.error("Logout error:", err);
      }
    }

    localStorage.removeItem("token");
    localStorage.removeItem("user");

    setToken(null);
    setUser(null);
    setIsAuthenticated(false);
  };

  // NOT LOGGED IN → Show login or register
  if (!isAuthenticated) {
    return showLogin ? (
      <Login
        onLoginSuccess={handleLoginSuccess}
        onSwitchToRegister={() => setShowLogin(false)}
      />
    ) : (
      <Register
        onRegisterSuccess={handleRegisterSuccess}
        onSwitchToLogin={() => setShowLogin(true)}
      />
    );
  }

  // LOGGED IN → Show songs page
  return (
    <main>
      <Songs token={token!} user={user} onLogout={handleLogout} />
    </main>
  );
}
