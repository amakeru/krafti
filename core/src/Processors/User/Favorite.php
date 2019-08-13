<?php

namespace App\Processors\User;

use App\Model\Course;
use App\Model\UserFavorite;
use App\ObjectProcessor;

class Favorite extends \App\Processor
{

    public function get()
    {
        $favorites = [];
        /** @var UserFavorite $obj */
        foreach ($this->container->user->favorites()->get() as $obj) {
            $favorites[] = $obj->course_id;
        }
        $courses = Course::query()->whereIn('id', $favorites)->where(['active' => true]);
        $processor = new \App\Processors\Web\Courses($this->container);

        $rows = [];
        if ($total = $courses->count()) {
            foreach ($courses->get() as $course) {
                $rows[] = $processor->prepareRow($course);
            }
        }

        return $this->success([
            'total' => $total,
            'rows' => $rows,
        ]);
    }


    public function put()
    {
        if (!$course_id = (int)$this->getProperty('course_id')) {
            return $this->failure('Вы должны указать id курса для добавления в избранное');
        }

        $key = [
            'user_id' => $this->container->user->id,
            'course_id' => $course_id,
        ];
        try {
            if (!UserFavorite::query()->where($key)->count()) {
                $obj = new UserFavorite($key);
                $obj->save();
            }
        } catch (\Exception $e) {
            return $this->failure($e->getMessage());
        }

        return (new Profile($this->container))->get();
    }


    public function delete()
    {
        if (!$course_id = (int)$this->getProperty('course_id')) {
            return $this->failure('Вы должны указать id курса для добавления в избранное');
        }

        $key = [
            'user_id' => $this->container->user->id,
            'course_id' => $course_id,
        ];
        try {
            /** @var UserFavorite $obj */
            if ($obj = UserFavorite::query()->where($key)->first()) {
                $obj->delete();
            }
        } catch (\Exception $e) {
            return $this->failure($e->getMessage());
        }

        return (new Profile($this->container))->get();
    }

}
