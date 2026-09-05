#!/usr/bin/env python3
"""Generate the four Phase 07-04 city-scene art assets with the Gemini API.

Usage:

    set -a; source /Users/sierra/Dev/Jogos/.env; set +a
    python3 tools/generate-city-assets.py

The API key comes ONLY from the `GEMINI_API_KEY` environment variable — never
hardcode it here, never commit it. Each generated PNG ships with a sibling
`<name>.prompt.md` recording the model id actually used, the ISO date and the
complete prompt, per the project's asset-provenance rule
(`/Users/sierra/Dev/Jogos/CLAUDE.md`).
"""

from __future__ import annotations

import datetime
import os
import sys
from pathlib import Path

from google import genai
from google.genai import types
from PIL import Image

MODEL_ID = "gemini-3.1-flash-image"

OUTPUT_DIR = Path(__file__).resolve().parent.parent / "apps" / "mobile" / "assets" / "city"

STYLE_BIBLE = (
    "Historical empire, military strategy, a living map, a premium modern "
    "interface. Rich without becoming a medieval carnival of glowing buttons. "
    "Original identity — do not imitate any existing game. Materials: parchment, "
    "stone, bronze, gold, steel, wood, deep blue, military red, dramatic "
    "lighting, metallic detail. No text, no UI chrome, no watermark."
)

CHROMA_INSTRUCTION = (
    "Background: a single solid, flat, uniform chroma-key green (#00FF00), "
    "filling the entire frame edge to edge with no gradient, no vignette and no "
    "texture. The subject casts no shadow on the ground and touches no other "
    "surface."
)

ASSETS = [
    {
        "name": "city_ground",
        "subject": (
            "A walled city courtyard floor seen from directly above: packed "
            "earth, stone paving, low retaining walls marking empty building "
            "plots. Roughly 1:1 composition, low contrast, quiet — it is the "
            "ground the plots sit on and must never out-compete them."
        ),
        "background": "Painted scenery, no chroma — a rendered top-down ground texture.",
        "chroma": False,
    },
    {
        "name": "slot_empty_icon",
        "subject": (
            "A single empty building foundation: a bare stone footing outline "
            "on packed earth. Simple silhouette readable at 64-128px. Not a "
            "building — just the empty foundation footprint."
        ),
        "background": CHROMA_INSTRUCTION,
        "chroma": True,
    },
    {
        "name": "slot_category_core",
        "subject": (
            "A keep/palace silhouette icon — a squat crenellated stone tower "
            "with a single banner. Distinct, bold outline, no interior detail, "
            "reads as a strong graphic mark rather than a rendered building."
        ),
        "background": CHROMA_INSTRUCTION,
        "chroma": True,
    },
    {
        "name": "slot_category_economy",
        "subject": (
            "A granary/storehouse silhouette icon — a wide pitched-roof barn "
            "with a grain sack motif. Bold outline clearly different in shape "
            "from a tower or keep at small size, reads as a strong graphic "
            "mark rather than a rendered building."
        ),
        "background": CHROMA_INSTRUCTION,
        "chroma": True,
    },
]


def full_prompt(asset: dict) -> str:
    return f"{STYLE_BIBLE}\n\nSubject: {asset['subject']}\n\n{asset['background']}"


def generate_image(client: genai.Client, prompt: str) -> bytes:
    response = client.models.generate_content(
        model=MODEL_ID,
        contents=prompt,
        config=types.GenerateContentConfig(
            response_modalities=["IMAGE"],
            image_config=types.ImageConfig(aspect_ratio="1:1"),
        ),
    )

    candidates = response.candidates or []
    if not candidates or candidates[0].content is None:
        raise RuntimeError("Gemini returned no candidates for this prompt.")

    for part in candidates[0].content.parts or []:
        if part.inline_data is not None and part.inline_data.data:
            return part.inline_data.data

    raise RuntimeError("Gemini response contained no inline image data.")


def remove_chroma_and_crop(png_path: Path) -> None:
    """Alpha-composite out the #00FF00 chroma and crop to content.

    The model is never asked for transparency directly — it returns grey.
    Instead every solidly-green pixel becomes fully transparent, and the
    canvas is cropped to the resulting alpha bounding box.
    """
    image = Image.open(png_path).convert("RGBA")
    pixels = image.load()
    width, height = image.size

    for y in range(height):
        for x in range(width):
            r, g, b, a = pixels[x, y]
            if g > 180 and r < 120 and b < 120:
                pixels[x, y] = (r, g, b, 0)

    bbox = image.getbbox()
    if bbox is not None:
        image = image.crop(bbox)

    image.save(png_path)


def write_provenance(asset: dict, prompt: str, iso_date: str) -> None:
    prompt_path = OUTPUT_DIR / f"{asset['name']}.prompt.md"
    prompt_path.write_text(
        "# Provenance\n\n"
        f"- **Model:** `{MODEL_ID}`\n"
        f"- **Date:** {iso_date}\n"
        f"- **Asset:** `{asset['name']}.png`\n\n"
        "## Full prompt\n\n"
        f"```\n{prompt}\n```\n",
        encoding="utf-8",
    )


def main() -> int:
    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        print("GEMINI_API_KEY is not set in the environment. Aborting.", file=sys.stderr)
        return 1

    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    client = genai.Client(api_key=api_key)
    iso_date = datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%d")

    for asset in ASSETS:
        prompt = full_prompt(asset)
        print(f"Generating {asset['name']}.png with {MODEL_ID}...")
        image_bytes = generate_image(client, prompt)

        png_path = OUTPUT_DIR / f"{asset['name']}.png"
        png_path.write_bytes(image_bytes)

        if asset["chroma"]:
            remove_chroma_and_crop(png_path)

        write_provenance(asset, prompt, iso_date)
        print(f"  wrote {png_path}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
