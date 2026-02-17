CREATE TABLE pokemon (
  id INT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  sprite_url VARCHAR(255),
  generation SMALLINT NOT NULL,
  elo_rating INT NOT NULL DEFAULT 1000,
  vote_count INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE votes (
  id BIGSERIAL PRIMARY KEY,
  winner_id INT NOT NULL REFERENCES pokemon(id),
  loser_id INT NOT NULL REFERENCES pokemon(id),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  session_hash VARCHAR(64)
);

CREATE INDEX idx_votes_created_at ON votes(created_at);
CREATE INDEX idx_pokemon_elo ON pokemon(elo_rating);
