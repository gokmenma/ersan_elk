"use client";

import { useEffect } from "react";

export default function IlanGoruntulemeSayaci({ token }: { token: string }) {
  useEffect(() => {
    const anahtar = `ilan_gor_${token}`;
    try {
      if (sessionStorage.getItem(anahtar)) return;
    } catch {}
    fetch("/api/kariyer/goruntule", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ token }),
      keepalive: true,
    })
      .then((yanit) => {
        if (!yanit.ok) return;
        try {
          sessionStorage.setItem(anahtar, "1");
        } catch {}
      })
      .catch(() => {});
  }, [token]);

  return null;
}
