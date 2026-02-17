import requests
import time
import json

START = 1
END = 493
DELAY = 0.3
MAX_RETRIES = 3

pokemon_data = []

print("Seeding Gen 1–4 Pokémon into JSON...")

for poke_id in range(START, END + 1):
    url = f"https://pokeapi.co/api/v2/pokemon/{poke_id}"
    attempt = 0
    data = None

    while attempt < MAX_RETRIES:
        try:
            response = requests.get(url, timeout=10)
            response.raise_for_status()
            data = response.json()
            break
        except Exception as e:
            attempt += 1
            print(f"Retry {attempt} for #{poke_id}...")
            time.sleep(1)

    if not data:
        print(f"Failed permanently: #{poke_id}")
        continue

    generation = (
        1 if poke_id <= 151 else
        2 if poke_id <= 251 else
        3 if poke_id <= 386 else
        4
    )

    pokemon_data.append({
        "id": data["id"],
        "name": data["name"],
        "generation": generation,
        "sprite_url": (
            data["sprites"]["other"]["official-artwork"]["front_default"]
            or data["sprites"]["front_default"]
        )
    })

    print(f"Fetched {data['name']} (Gen {generation})")

    time.sleep(DELAY)

with open("pokemon_gen1_4.json", "w") as f:
    json.dump(pokemon_data, f, indent=2)

print("Done. Data saved to pokemon_gen1_4.json")

