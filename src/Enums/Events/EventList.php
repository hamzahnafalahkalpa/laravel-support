<?php

namespace Hanafalah\LaravelSupport\Enums\Events;

enum EventList: string
{
    case SAVING   = 'saving';
    case SAVED    = 'saved';
    case CREATING = 'creating';
    case CREATED  = 'created';
    case UPDATING = 'updating';
    case UPDATED  = 'updated';
    case DELETING = 'deleting';
    case DELETED  = 'deleted';
}
