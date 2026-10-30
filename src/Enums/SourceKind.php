<?php

declare(strict_types=1);

namespace Atannex\Lexicon\Enums;

enum SourceKind: string
{
    case OralInterview = 'oral_interview';
    case NativeSpeaker = 'native_speaker';
    case PublishedDictionary = 'published_dictionary';
    case Manuscript = 'manuscript';
    case Book = 'book';
    case Archive = 'archive';
    case AudioRecording = 'audio_recording';
    case VideoRecording = 'video_recording';
    case FieldNotes = 'field_notes';
    case AcademicPublication = 'academic_publication';
    case CommunityVerification = 'community_verification';
    case Other = 'other';
}
