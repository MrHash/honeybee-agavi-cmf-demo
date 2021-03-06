<?php

namespace HBDemo\Commenting\Fixture;

use Honeybee\Infrastructure\Fixture\Fixture;
use Honeybee\Infrastructure\Fixture\FixtureTargetInterface;

class Fixture_20150810231335_InitialTestData extends Fixture
{
    public function import(FixtureTargetInterface $fixture_target)
    {
        $this->copyFixtureAssetsToTempLocation(__DIR__ . DIRECTORY_SEPARATOR . 'assets');

        foreach ($this->getFixtureFiles() as $filename) {
            $this->importFixtureFile($filename);
        }
    }

    protected function getFixtureFiles()
    {
        return [
            __DIR__ . DIRECTORY_SEPARATOR . 'owner-data.json',
            __DIR__ . DIRECTORY_SEPARATOR . 'account-data.json',
            __DIR__ . DIRECTORY_SEPARATOR . 'topic-data.json',
            __DIR__ . DIRECTORY_SEPARATOR . 'comment-data.json'
        ];
    }
}
