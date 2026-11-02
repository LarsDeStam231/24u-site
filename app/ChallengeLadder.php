<?php

namespace App;

class ChallengeLadder
{
    /**
     * @param  list<array{amountCents: int, name: string, description: string}>  $definedChallenges
     * @return list<array{amountCents: int, name: string, description: string, isFeatured: bool, isHeadline: bool}>
     */
    public function build(array $definedChallenges): array
    {
        $challengesByAmount = [];

        foreach ($definedChallenges as $challenge) {
            $challengesByAmount[$challenge['amountCents']] = [
                ...$challenge,
                'isFeatured' => $challenge['amountCents'] >= 50_000 && $challenge['amountCents'] % 50_000 === 0,
                'isHeadline' => $challenge['amountCents'] >= 100_000 && $challenge['amountCents'] % 100_000 === 0,
            ];
        }

        ksort($challengesByAmount);

        return array_values($challengesByAmount);
    }
}
