<?php

namespace App\Services\Files;

class JobNameGenerator
{
    public static function generate(): string
    {
        $adjectives = ['swift', 'bright', 'stellar', 'cosmic', 'quantum', 'digital', 'cyber', 'turbo', 'mega', 'ultra'];
        $nouns = ['pulse', 'wave', 'stream', 'flow', 'burst', 'beam', 'spark', 'flash', 'surge', 'blast'];

        $adjective = $adjectives[array_rand($adjectives)];
        $noun = $nouns[array_rand($nouns)];

        return sprintf('%s-%s-%s', $adjective, $noun, substr(md5(microtime()), rand(0, 26), 5));
    }
}
