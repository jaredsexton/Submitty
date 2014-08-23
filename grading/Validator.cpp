/* FILENAME: Validator.cpp
 * YEAR: 2014
 * AUTHORS:
 *   Members of Rensselaer Center for Open Source (rcos.rpi.edu):
 *   Chris Berger
 *   Jesse Freitas
 *   Severin Ibarluzea
 *   Kiana McNellis
 *   Kienan Knight-Boehm
 *   Sam Seng
 * LICENSE: Please refer to 'LICENSE.md' for the conditions of using this code
 *
 * RELEVANT DOCUMENTATION:
 *
*/

#include <iostream>
#include <fstream>
#include <sstream>
#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <vector>
#include <string>
#include <iterator>
#include <typeinfo>
#include <sys/types.h>
#include <sys/stat.h>
#include <math.h>
#include <unistd.h>

#include "modules/modules.h"
#include "grading/TestCase.h"

#include "grading/TestCase.cpp"  /* Should not #include a .cpp file */

bool checkValidDirectory(char *directory);
bool checkValidDirectory(const char *directory);
int validateTestCases(int subnum, const char *subtime/*, int readme,
						       int compiled*/);

int main(int argc, char *argv[]) {

  /* Check argument usage */
  if (argc != 4) {
#ifdef DEBUG
    std::cerr << "VALIDATOR USAGE: validator <submission#> "
      "<time-of-submission> <runner-result>" << std::endl;
#endif
    return 1;
  }
  

  // TODO: Apply a diff to readme?


  // Run test cases
  int rc =
    validateTestCases(atoi(argv[1]), argv[2] /*, readme_found, atoi(argv[3])*/);
  if (rc > 0) {
#ifdef DEBUG
    std::cerr << "Validator terminated" << std::endl;
#endif
    return 1;
  }

  return 0;
}



/* Ensures that the given directory exists */
bool checkValidDirectory(char *directory) {

  if (access(directory, 0) == 0) {
    struct stat status;
    stat(directory, &status);
    if (status.st_mode & S_IFDIR) {
#ifdef DEBUG
      std::cout << "Directory " << directory << " found!" << std::endl;
#endif
      return true;
    }
  }
#ifdef DEBUG
  std::cerr << "ERROR: directory " << directory << " does not exist"
            << std::endl;
#endif
  return false;
}



// checkValidDirectory with const char*
bool checkValidDirectory(const char *directory) {

  if (access(directory, 0) == 0) {
    struct stat status;
    stat(directory, &status);
    if (status.st_mode & S_IFDIR) {
#ifdef DEBUG
      std::cout << "Directory " << directory << " found!" << std::endl;
#endif
      return true;
    }
  }
#ifdef DEBUG
  std::cerr << "ERROR: directory " << directory << " does not exist"
            << std::endl;
#endif
  return false;
}



/* Runs through each test case, pulls in the correct files, validates,
 and outputs the results */
int validateTestCases(int subnum, const char *subtime /*, int readme,
							 int compiled*/) {

  int total_grade = 0;

  std::stringstream testcase_json;

  for (int i = 0; i < num_testcases; ++i) {
    
    std::cout << testcases[i].title() << " - points: " << testcases[i].points()
              << std::endl;

    int testcase_grade = 0;
    
    std::string message = "";
    // --------------------------------------------
    if (testcases[i].isFileExistsTest()  ||  testcases[i].isCompilationTest()) {
      std::cerr << "THIS IS A FILE EXISTS TEST! " << std::endl;
      if ( access( testcases[i].details().c_str(), F_OK|R_OK|W_OK ) != -1 ) { /* file exists */
	std::cerr << "file does exist: " << testcases[i].details() << std::endl;
	testcase_grade = testcases[i].points();
	message += testcases[i].details() + " was found";
      } else {
	std::cerr << "ERROR file DOES NOT exist: " << testcases[i].details() << std::endl;
	message += "ERROR: " + testcases[i].details() + " was NOT FOUND!";
      }
    }
    // --------------------------------------------    
    else {

      float grade_helper = 1.0;
      
      for (int j = 0; j < testcases[i].numFileComparisons(); j++) {

	//std::cout << "-------------------" << std::endl;
	//std::cout << "comparison #" << j << std::endl;

	// Pull in student output & expected output
	std::ifstream student_instr(testcases[i].filename(j).c_str());
	if (!student_instr) {
	  std::stringstream tmp;
	  tmp << "ERROR: comparison #" << j << ": Student's " << testcases[i].filename(j) << " does not exist";
	  std::cerr << tmp.str() << std::endl;
	  //message += tmp.str();
	} 
	std::ifstream expected_instr(testcases[i].expected(j).c_str());
	if (!expected_instr) {
	  std::stringstream tmp;
	  tmp << "ERROR: comparison #" << j << ": Instructor's " + testcases[i].expected(j) + " does not exist!";
	  std::cerr << tmp.str() << std::endl;
	  //message += tmp.str();
	}
	TestResults *result = testcases[i].compare(j);

	std::stringstream diff_path;
	diff_path << testcases[i].prefix() << "_" << j << "_diff.json";
	std::ofstream diff_stream(diff_path.str().c_str());
	  
	std::cout << "result->grade() " << result->grade() << std::endl;

	grade_helper *= result->grade();
	result->printJSON(diff_stream);
	  
	std::cout << "Grade: " << testcase_grade << std::endl;
	  
	delete result;
      }
      
      testcase_grade = (int)floor(grade_helper * testcases[i].points());
    }      
    
    const char *last_line = (i == num_testcases - 1) ? "\t\t}\n" : "\t\t},\n";

    total_grade += testcase_grade;

    // Generate JSON data
    testcase_json << "\t\t{\n"
                  << "\t\t\t\"test_name\": \"" << testcases[i].title()
                  << "\",\n"
                  << "\t\t\t\"points_awarded\": " << testcase_grade << ",\n";


    if (testcases[i].numFileComparisons() > 0)  {
      testcase_json << "\t\t\t\"diffs\": [\n";

      for (int j = 0; j < testcases[i].numFileComparisons(); j++) {
	
	std::stringstream expected_path;
	expected_path << expected_out_dir << testcases[i].expected(j);
	
	testcase_json
	  << "\t\t\t\t{\n"
	  << "\t\t\t\t\t\"diff_id\":\"" << testcases[i].prefix() << "_" << j << "_diff\",\n"
	  << "\t\t\t\t\t\"student_file\":\"" << testcases[i].filename(j) << "\",\n";

	if (1) { //testcases[i].expected(j) != "") {
	  testcase_json << "\t\t\t\t\t\"instructor_file\":\"" << expected_path.str() << "\",\n"
			<< "\t\t\t\t\t\"difference\":\"" << testcases[i].prefix() << "_" << j << "_diff.json\",\n";
	}

	if (1) { //message != "") {
	  testcase_json << "\t\t\t\t\t\"message\": \"this is comparison #" << j << " " << message << "\",\n";
	  message = "";
	}
	
	testcase_json
	  << "\t\t\t\t},\n";
      }
      
      testcase_json << "\t\t\t],\n";
    }
    if (1) { //message != "") {
      testcase_json << "\t\t\t\"message\": \"message for this testcase... " << message << "\",\n";
    }
    if (testcases[i].isCompilationTest()) {
      testcase_json << "\t\t\t\"compilation_output\": \".submit_compilation_output.txt\",\n";
    }
    
    testcase_json << last_line;
  }

  /* Output total grade */
  std::string grade_path = "grade.txt";
  std::ofstream gradefile(grade_path.c_str());
  gradefile << total_grade << std::endl;

  /* Generate submission.json */
  std::ofstream json_file("submission.json");
  json_file << "{\n"
            << "\t\"submission_number\": " << subnum << ",\n"
            << "\t\"points_awarded\": " << total_grade << ",\n"
            << "\t\"submission_time\": \"" << subtime << "\",\n"
            << "\t\"testcases\": [\n";


  json_file << testcase_json.str() << "\t]\n"
            << "}";
  json_file.close();

  return 0;
}