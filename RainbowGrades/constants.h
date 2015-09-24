#ifndef __CONSTANTS_H__
#define __CONSTANTS_H__

extern bool DISPLAY_INSTRUCTOR_NOTES;
extern bool DISPLAY_EXAM_SEATING;
extern bool DISPLAY_MOSS_DETAILS;
extern bool DISPLAY_FINAL_GRADE;
extern bool DISPLAY_GRADE_SUMMARY;
extern bool DISPLAY_GRADE_DETAILS;
extern bool DISPLAY_ICLICKER;



extern char GLOBAL_EXAM_TITLE[MAX_STRING_LENGTH];
extern char GLOBAL_EXAM_DATE[MAX_STRING_LENGTH];
extern char GLOBAL_EXAM_TIME[MAX_STRING_LENGTH];
extern char GLOBAL_EXAM_DEFAULT_ROOM[MAX_STRING_LENGTH];


bool validSection(int section);

#endif // __CONSTANTS_H__