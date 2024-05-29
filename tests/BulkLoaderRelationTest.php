<?php

namespace AntonyThorpe\Consumer\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\ArrayList;
use AntonyThorpe\Consumer\BulkLoader;
use AntonyThorpe\Consumer\ArrayBulkLoaderSource;
use AntonyThorpe\Consumer\Tests\Model\CourseSelection;
use AntonyThorpe\Consumer\Tests\Model\Course;

class BulkLoaderRelationTest extends SapphireTest
{
    protected static $fixture_file = ['Fixtures/BulkLoaderRelationTest.yml'];

    protected static $extra_dataobjects = [
        Course::class,
        CourseSelection::class
    ];

    protected $loader;

    //use the same source for all tests
    public function setUp(): void
    {
        parent::setUp();
        $data = [
             //unlinked relation, no record
            ["Course.Title" => "Math 101", "Term" => 1],
             //existing relation and record
            ["Course.Title" => "Tech 102", "Term" => 1],
             //relation does not exist, no record
            ["Course.Title" => "Geometry 722", "Term" => 1]
        ];
        $this->loader = BulkLoader::create(CourseSelection::class);
        $this->loader->setSource(
            new ArrayBulkLoaderSource($data)
        );
    }

    /**
     * This default behaviour should act the same as
     * testLinkAndCreateRelations
     */
    public function testEmptyBehaviour(): void
    {
        $results = $this->loader->load();
        $this->assertEquals(
            3,
            $results->CreatedCount(),
            "objs have been created from all records"
        );
        $this->assertEquals(
            4,
            Course::get()->count(),
            "New Geometry 722 course created"
        );
        $this->assertEquals(
            4,
            CourseSelection::get()->filter("CourseID:GreaterThan", 0)->count(),
            "we have gone from 1 to 4 linked records"
        );
    }

    public function testLinkAndCreateRelations(): void
    {
        $this->loader->transforms['Course.Title'] = [
            'link' => true,
            'create' => true
        ];
        $results = $this->loader->load();
        $this->assertEquals(
            3,
            $results->CreatedCount(),
            "objs have been created from all records"
        );
        $this->assertEquals(
            4,
            Course::get()->count(),
            "New Geometry 722 course created"
        );
        $this->assertEquals(
            4,
            CourseSelection::get()->filter("CourseID:GreaterThan", 0)->count(),
            "we have gone from 1 to 4 linked records"
        );
    }

    public function testNoRelations(): void
    {
        $this->loader->transforms['Course.Title'] = [
            'link' => false,
            'create' => false
        ];
        $results = $this->loader->load();
        $this->assertEquals(
            3,
            $results->CreatedCount(),
            "objs have been created from all records"
        );
        $this->assertEquals(
            3,
            Course::get()->count(),
            "No extra courses created"
        );
        $this->assertEquals(
            1,
            CourseSelection::get()->filter("CourseID:GreaterThan", 0)->count(),
            "No records have been linked"
        );
    }

    public function testOnlyLinkRelations(): void
    {
        $this->loader->transforms['Course.Title'] = [
            'link' => true,
            'create' => false
        ];
        $results = $this->loader->load();
        $this->assertEquals(
            3,
            $results->CreatedCount(),
            "objs have been created from all records"
        );
        $this->assertEquals(
            3,
            Course::get()->count(),
            "number of courses remains the same"
        );
        //asserting 3 and not 2 because we have no duplicate checks
        $this->assertEquals(
            3,
            CourseSelection::get()->filter("CourseID:GreaterThan", 0)->count(),
            "we have gone from 1 to 3 linked records"
        );
    }

    public function testOnlyCreateUniqueRelations(): void
    {
        $this->loader->transforms['Course.Title'] = [
            'link' => false,
            'create' => true
        ];
        $results = $this->loader->load();
        $this->assertEquals(
            3,
            $results->CreatedCount(),
            "objs have been created from all records"
        );
        $this->assertEquals(
            4,
            Course::get()->count(),
            "New Geometry 722 course created"
        );
        $this->assertEquals(
            2,
            CourseSelection::get()->filter("CourseID:GreaterThan", 0)->count(),
            "Only the created object is linked"
        );
    }

    public function testRelationDuplicateCheck(): void
    {
        $this->loader->transforms['Course.Title'] = [
            'link' => true,
            'create' => true
        ];
        $this->loader->duplicateChecks = [
            "Course.Title"
        ];
        $results = $this->loader->load();
        $this->assertEquals(2, $results->CreatedCount(), "2 created");
        $this->assertEquals(0, $results->SkippedCount(), "0 skipped");
        $this->assertEquals(1, $results->UpdatedCount(), "1 updated");

        //$this->markTestIncomplete("test using {RelationName}ID and {RelationName}");
    }

    public function testRelationList(): void
    {
        $list = ArrayList::create();
        $this->loader->transforms['Course.Title'] = [
            'create' => true,
            'link' => true,
            'list' => $list
        ];
        $results = $this->loader->load();
        $this->assertEquals(3, $results->CreatedCount(), "3 records created");
        $this->assertEquals(3, $list->count(), "3 relations created");

        //make sure re-run doesn't change relation list
        $results = $this->loader->load();
        $this->assertEquals(3, $results->CreatedCount(), "3 more records created");
        $this->assertEquals(3, $list->count(), "relation list count remains the same");
    }
}
