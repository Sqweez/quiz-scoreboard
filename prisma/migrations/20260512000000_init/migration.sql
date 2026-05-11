CREATE TABLE "games" (
  "id" UUID NOT NULL DEFAULT gen_random_uuid(),
  "user_id" UUID NOT NULL,
  "title" TEXT NOT NULL,
  "created_at" TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" TIMESTAMP(3) NOT NULL,

  CONSTRAINT "games_pkey" PRIMARY KEY ("id")
);

CREATE TABLE "rounds" (
  "id" UUID NOT NULL DEFAULT gen_random_uuid(),
  "game_id" UUID NOT NULL,
  "title" TEXT NOT NULL,
  "max_score" DOUBLE PRECISION,
  "questions_count" INTEGER,
  "position" INTEGER NOT NULL,

  CONSTRAINT "rounds_pkey" PRIMARY KEY ("id")
);

CREATE TABLE "teams" (
  "id" UUID NOT NULL DEFAULT gen_random_uuid(),
  "game_id" UUID NOT NULL,
  "name" TEXT NOT NULL,
  "position" INTEGER NOT NULL,

  CONSTRAINT "teams_pkey" PRIMARY KEY ("id")
);

CREATE TABLE "scores" (
  "id" UUID NOT NULL DEFAULT gen_random_uuid(),
  "team_id" UUID NOT NULL,
  "round_id" UUID NOT NULL,
  "value" DOUBLE PRECISION NOT NULL DEFAULT 0,

  CONSTRAINT "scores_pkey" PRIMARY KEY ("id")
);

CREATE INDEX "games_user_id_idx" ON "games"("user_id");
CREATE INDEX "rounds_game_id_idx" ON "rounds"("game_id");
CREATE INDEX "teams_game_id_idx" ON "teams"("game_id");
CREATE INDEX "scores_round_id_idx" ON "scores"("round_id");
CREATE UNIQUE INDEX "scores_team_id_round_id_key" ON "scores"("team_id", "round_id");

ALTER TABLE "rounds" ADD CONSTRAINT "rounds_game_id_fkey"
  FOREIGN KEY ("game_id") REFERENCES "games"("id") ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE "teams" ADD CONSTRAINT "teams_game_id_fkey"
  FOREIGN KEY ("game_id") REFERENCES "games"("id") ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE "scores" ADD CONSTRAINT "scores_team_id_fkey"
  FOREIGN KEY ("team_id") REFERENCES "teams"("id") ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE "scores" ADD CONSTRAINT "scores_round_id_fkey"
  FOREIGN KEY ("round_id") REFERENCES "rounds"("id") ON DELETE CASCADE ON UPDATE CASCADE;
