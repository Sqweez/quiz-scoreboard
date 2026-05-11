ALTER TABLE "games"
ADD COLUMN "status" TEXT NOT NULL DEFAULT 'draft';

CREATE INDEX "games_status_idx" ON "games" ("status");
