import type { Route } from "./+types/home";
import { Welcome } from "../welcome/welcome";
import Songs from "./songs";
import SongsForm from "./songsForm";


export function meta({}: Route.MetaArgs) {
  return [
    { title: "Cloud Music Player" },
    { name: "description", content: "Welcome to React Router!" },
  ];
}

export default function Home() {
    return (
    <main>
     
      
      {/* <SongsForm />  👈 renders your Laravel + React data here */}
      <Songs />  {/* 👈 renders your Laravel + React data here */}
    </main>
  );
}