#!/usr/bin/env python3
"""Licora Secure API v2 lifecycle reference.

This developer/test client creates an ephemeral device credential, exercises
activate -> status -> refresh -> status -> deactivate, then exits. Production
applications must persist the device private key and rotated refresh token in
OS-backed secure storage and verify LICORA-V2/RS256 access-token signatures
with the pinned Licora server public key before trusting token claims locally.
"""
from __future__ import annotations

import argparse
import base64
import hashlib
import json
import secrets
import time
import uuid
from urllib.parse import urljoin, urlparse

import requests
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec


def b64url(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).rstrip(b"=").decode("ascii")


def jwt_payload(token: str) -> dict:
    parts = token.split(".")
    if len(parts) != 3:
        raise RuntimeError("Licora returned a malformed access token")
    raw = parts[1] + "=" * ((4 - len(parts[1]) % 4) % 4)
    return json.loads(base64.urlsafe_b64decode(raw.encode("ascii")))


def canonical(method: str, path: str, timestamp: int, nonce: str, body: bytes, context: str) -> bytes:
    return "\n".join([
        method.upper(), path, str(timestamp), nonce,
        hashlib.sha256(body).hexdigest().lower(), context,
    ]).encode("utf-8")


def compact_json(value: dict) -> bytes:
    return json.dumps(value, separators=(",", ":"), ensure_ascii=False).encode("utf-8")


class LicoraV2:
    def __init__(self, base_url: str, app_id: str, app_version: str) -> None:
        self.base_url = base_url.rstrip("/") + "/"
        self.app_id = app_id
        self.app_version = app_version
        self.private_key = ec.generate_private_key(ec.SECP256R1())
        self.public_pem = self.private_key.public_key().public_bytes(
            serialization.Encoding.PEM,
            serialization.PublicFormat.SubjectPublicKeyInfo,
        ).decode("ascii")
        self.device_id = "py-" + uuid.uuid4().hex

    def endpoint(self, name: str) -> str:
        return urljoin(self.base_url, f"api/v2/{name}.php")

    def proof_headers(self, url: str, body: bytes, context: str, access_token: str | None = None) -> dict[str, str]:
        timestamp = int(time.time())
        nonce = b64url(secrets.token_bytes(18))
        path = urlparse(url).path or "/"
        signature = self.private_key.sign(
            canonical("POST", path, timestamp, nonce, body, context),
            ec.ECDSA(hashes.SHA256()),
        )
        headers = {
            "Content-Type": "application/json",
            "X-Licora-Timestamp": str(timestamp),
            "X-Licora-Nonce": nonce,
            "X-Licora-Device-Signature": b64url(signature),
        }
        if access_token:
            headers["Authorization"] = "Bearer " + access_token
        return headers

    def post(self, name: str, payload: dict, context: str, access_token: str | None = None) -> dict:
        url = self.endpoint(name)
        body = compact_json(payload)
        response = requests.post(
            url,
            data=body,
            headers=self.proof_headers(url, body, context, access_token),
            timeout=20,
        )
        try:
            data = response.json()
        except ValueError as exc:
            raise RuntimeError(f"HTTP {response.status_code}: non-JSON response") from exc
        if not data.get("success"):
            raise RuntimeError(f"Licora error {data.get('code', 'UNKNOWN')} (HTTP {response.status_code})")
        return data

    def activate(self, license_key: str) -> dict:
        payload = {
            "license_key": license_key,
            "app_id": self.app_id,
            "app_version": self.app_version,
            "device_id": self.device_id,
            "device_public_key": self.public_pem,
        }
        return self.post("activate", payload, "activate:" + self.app_id)

    def status(self, access_token: str) -> dict:
        jti = str(jwt_payload(access_token)["jti"])
        return self.post("status", {}, jti, access_token)

    def refresh(self, refresh_token: str) -> dict:
        payload = {"refresh_token": refresh_token, "app_version": self.app_version}
        context = "refresh:" + hashlib.sha256(refresh_token.encode("utf-8")).hexdigest()
        return self.post("refresh", payload, context)

    def deactivate(self, access_token: str) -> dict:
        jti = str(jwt_payload(access_token)["jti"])
        return self.post("deactivate", {}, jti, access_token)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", required=True)
    parser.add_argument("--app-id", required=True)
    parser.add_argument("--license-key", required=True)
    parser.add_argument("--app-version", default="1.0.0")
    args = parser.parse_args()

    client = LicoraV2(args.base_url, args.app_id, args.app_version)
    access_token = ""
    try:
        activated = client.activate(args.license_key)
        access_token = str(activated["access_token"])
        refresh_token = str(activated["refresh_token"])
        print("[PASS] activate", activated["code"])

        current = client.status(access_token)
        print("[PASS] status", current["code"], current.get("license", {}).get("status"))

        refreshed = client.refresh(refresh_token)
        access_token = str(refreshed["access_token"])
        refresh_token = str(refreshed["refresh_token"])
        print("[PASS] refresh", refreshed["code"], "rotated refresh token")

        current = client.status(access_token)
        print("[PASS] status-after-refresh", current["code"])

        client.deactivate(access_token)
        access_token = ""
        print("[PASS] deactivate")
        return 0
    finally:
        if access_token:
            try:
                client.deactivate(access_token)
                print("[INFO] cleanup deactivate completed")
            except Exception as exc:  # cleanup only
                print("[WARN] cleanup deactivate failed:", exc)


if __name__ == "__main__":
    raise SystemExit(main())
