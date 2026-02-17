import fs from "fs";

const START = 1;
const END = 493;
const DELAY = 300;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function fetchPokemon(id) {
  const url = `https://pokeapi.co/api/v2/pokemon/${id}`;

  try {
    const res = await fetch(url);

    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }

    return await res.json();
  } catch (err) {
    console.error(`Failed for #${id}:`, err.message);
    return null;
  }
}

async function main() {
  const results = [];

  console.log("Seeding Gen 1–4 via Node...");

  for (let id = START; id <= END; id++) {
    const data = await fetchPokemon(id);

    if (!data) continue;

    const generation =
      id <= 151 ? 1 :
      id <= 251 ? 2 :
      id <= 386 ? 3 : 4;

    results.push({
      id: data.id,
      name: data.name,
      generation,
      sprite_url:
        data.sprites.other["official-artwork"].front_default ||
        data.sprites.front_default
    });

    console.log(`Fetched ${data.name}`);
    await sleep(DELAY);
  }

  fs.writeFileSync(
    "pokemon_gen1_4.json",
    JSON.stringify(results, null, 2)
  );

  console.log("Done. Saved to pokemon_gen1_4.json");
}

main();

