<?php

namespace app\controllers\forum;

use app\libraries\Core;
use app\controllers\AbstractController;
use app\libraries\Output;
use app\libraries\Utils;
use app\libraries\FileUtils;

/**
 * Class ForumHomeController
 *
 * Controller to deal with the submitty home page. Once the user has been authenticated, but before they have
 * selected which course they want to access, they are forwarded to the home page.
 */
class ForumController extends AbstractController {

	/**
     * ForumHomeController constructor.
     *
     * @param Core $core
     */
    public function __construct(Core $core) {
        parent::__construct($core);
    }

    public function run() {
        switch ($_REQUEST['page']) {
            case 'create_thread':
                $this->showCreateThread();
                break;
            case 'publish_thread':
                $this->publishThread();
                break;
            case 'make_announcement':
                $this->alterAnnouncement(1);
                break;
            case 'publish_post':
                $this->publishPost();
                break;
            case 'delete_post':
                $this->alterPost(0);
                break;
            case 'edit_post':
                $this->alterPost(1);
                break;
            case 'get_edit_post_content':
                $this->getEditPostContent();
                break;
            case 'remove_announcement':
                $this->alterAnnouncement(0);
                break;
            case 'show_stats':
                $this->showStats();
                break;
            case 'view_thread':
            default:
                $this->showThreads();
                break;
        }
    }


    private function returnUserContentToPage($error, $isThread, $thread_id){
            //Notify User
            $this->core->addErrorMessage($error);
            if($isThread){
                $url = $this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread'));
            } else {
                $url = $this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id));
            }

            $this->core->redirect($url);
    }


    private function checkGoodAttachment($isThread, $thread_id, $file_post){
        if(count($_FILES[$file_post]['tmp_name']) > 5) {
            $this->returnUserContentToPage("Max file upload size is 5. Please try again.", $isThread, $thread_id);
            return -1;
        }
        $imageCheck = Utils::checkUploadedImageFile($file_post) ? 1 : 0;
        if($imageCheck == 0 && !empty($_FILES[$file_post]['tmp_name'])){
            $this->returnUserContentToPage("Invalid file type. Please upload only image files. (PNG, JPG, GIF, BMP...)", $isThread, $thread_id);
            return -1;
        
        } return $imageCheck;
    }

    //CODE WILL BE CONSOLIDATED IN FUTURE

    public function publishThread(){
        $title = $_POST["title"];
        $thread_content = str_replace("\r", "", $_POST["thread_content"]);
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        $announcment = (isset($_POST["Announcement"]) && $_POST["Announcement"] == "Announcement" && $this->core->getUser()->getGroup() < 3) ? 1 : 0 ;
        if(empty($title) || empty($thread_content)){
            $this->core->addErrorMessage("One of the fields was empty. Please re-submit your thread.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'create_thread')));
        } else {
            $hasGoodAttachment = $this->checkGoodAttachment(true, -1, 'file_input');
            if($hasGoodAttachment == -1){
                return;
            }
            
            $result = $this->core->getQueries()->createThread($this->core->getUser()->getId(), $title, $thread_content, $anon, $announcment, $hasGoodAttachment);
            $id = $result["thread_id"];
            $post_id = $result["post_id"];

            if($hasGoodAttachment == 1) {

                $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $id);
                FileUtils::createDir($thread_dir);

                $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                FileUtils::createDir($post_dir);

                for($i = 0; $i < count($_FILES["file_input"]["name"]); $i++){
                    $target_file = $post_dir . "/" . basename($_FILES["file_input"]["name"][$i]);
                    move_uploaded_file($_FILES["file_input"]["tmp_name"][$i], $target_file);
                }
                
            }
            
        }
        $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $id)));
    }

    public function publishPost(){
        $parent_id = (!empty($_POST["parent_id"])) ? htmlentities($_POST["parent_id"], ENT_QUOTES | ENT_HTML5, 'UTF-8') : -1;
        $post_content_tag = 'post_content';
        $file_post = 'file_input';
        if(empty($_POST['post_content'])){
            $post_content_tag .= ('_' . $parent_id);
            $file_post .= ('_' . $parent_id);
        }
        $post_content = str_replace("\r", "", $_POST[$post_content_tag]);
        $thread_id = htmlentities($_POST["thread_id"], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $anon = (isset($_POST["Anon"]) && $_POST["Anon"] == "Anon") ? 1 : 0;
        if(empty($post_content) || empty($thread_id)){
            $this->core->addErrorMessage("There was an error submitting your post. Please re-submit your post.");
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread')));
        } else {
            $hasGoodAttachment = $this->checkGoodAttachment(false, $thread_id, $file_post);
            if($hasGoodAttachment == -1){
                return;
            }
            $post_id = $this->core->getQueries()->createPost($this->core->getUser()->getId(), $post_content, $thread_id, $anon, 0, false, $hasGoodAttachment, $parent_id);
            $thread_dir = FileUtils::joinPaths(FileUtils::joinPaths($this->core->getConfig()->getCoursePath(), "forum_attachments"), $thread_id);
            if($hasGoodAttachment == 1) {
                $post_dir = FileUtils::joinPaths($thread_dir, $post_id);
                FileUtils::createDir($post_dir);
                for($i = 0; $i < count($_FILES[$file_post]["name"]); $i++){
                    $target_file = $post_dir . "/" . basename($_FILES[$file_post]["name"][$i]);
                    move_uploaded_file($_FILES[$file_post]["tmp_name"][$i], $target_file);
                }
            }
            $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id)));
        }
    }

    public function alterAnnouncement($type){
        if($this->core->getUser()->getGroup() <= 2){
            $thread_id = $_POST["thread_id"];
            $this->core->getQueries()->setAnnouncement($thread_id, $type);
        } else {
            $this->core->addErrorMessage("You do not have permissions to do that.");
        }
    }

    public function alterPost($modifyType){
        if($this->core->getUser()->getGroup() <= 2){

            if($modifyType == 0) { //delete post
                $thread_id = $_POST["thread_id"];
                $post_id = $_POST["post_id"];
                $type = "";
                if($this->core->getQueries()->deletePost($post_id, $thread_id)){
                    $type = "thread";
                } else {
                    $type = "post";
                }
                $this->core->getOutput()->renderJson(array('type' => $type));
            } else if($modifyType == 1) { //edit post
                $thread_id = $_POST["edit_thread_id"];
                $post_id = $_POST["edit_post_id"];
                $new_post_content = $_POST["edit_post_content"];
                if(!$this->core->getQueries()->editPost($post_id, $new_post_content)){
                    $this->core->addErrorMessage("There was an error trying to modify the post. Please try again.");
                } $this->core->redirect($this->core->buildUrl(array('component' => 'forum', 'page' => 'view_thread', 'thread_id' => $thread_id)));
            }
            $response = array('type' => $type);
            $this->core->getOutput()->renderJson($response);
            return $response;
        } else {
            $this->core->addErrorMessage("You do not have permissions to do that.");
        }
    }

    public function showThreads(){
        $user = $this->core->getUser()->getId();


        //NOTE: This section of code is neccesary until I find a better query 
        //To link the two sets together as the query function doesn't
        //support parenthesis starting a query 
        $announce_threads = $this->core->getQueries()->loadThreads(1);
        $reg_threads = $this->core->getQueries()->loadThreads(0);
        $threads = array_merge($announce_threads, $reg_threads);
        //END

        $current_user = $this->core->getUser()->getId();

        $posts = null;
        if(isset($_REQUEST["thread_id"])){
            $posts = $this->core->getQueries()->getPostsForThread($current_user, $_REQUEST["thread_id"]);
        } else {
            //We are at the "Home page"
            //Show the first post
            $posts = $this->core->getQueries()->getPostsForThread($current_user, -1);
            
        }
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'showForumThreads', $user, $posts, $threads);
    }

    public function showCreateThread(){
         $this->core->getOutput()->renderOutput('forum\ForumThread', 'createThread');
    }

    public function getEditPostContent(){
        $post_id = $_POST["post_id"];
        if($this->core->getUser()->getGroup() <= 2 && !empty($post_id)) {
            $result = $this->core->getQueries()->getPost($post_id);
            $output = array();
            $output['user'] = $result["author_user_id"];
            $output['post'] = $result["content"];
            $output['post_time'] = $result['timestamp'];
            $this->core->getOutput()->renderJson($output);
            return $output;
        } else {
            $this->core->getOutput()->renderJson(array('error' => "You do not have permissions to do that."));
        }
    }


    public function showStats(){
        $posts = array();
        $posts = $this->core->getQueries()->getPosts();
        $num_posts = count($posts);
        $function_date = 'date_format';
        $num_threads = 0;
        $users = array();
        for($i=0;$i<$num_posts;$i++){
            $user = $posts[$i]["author_user_id"];
            $content = $posts[$i]["content"];
            if(!isset($users[$user])){
                $users[$user] = array();
                $u = $this->core->getQueries()->getSubmittyUser($user);
                $users[$user]["first_name"] = htmlspecialchars($u -> getFirstName());
                $users[$user]["last_name"] = htmlspecialchars($u -> getLastName());
                $users[$user]["posts"]=array();
                $users[$user]["id"]=array();
                $users[$user]["timestamps"]=array();
                $users[$user]["total_threads"]=0;
                $users[$user]["num_deleted_posts"] = count($this->core->getQueries()->getDeletedPostsByUser($user)); 
            }
            if($posts[$i]["parent_id"]==-1){
                $users[$user]["total_threads"]++;
            }
            $users[$user]["posts"][] = $content;
            $users[$user]["id"][] = $posts[$i]["id"];
            $date = date_create($posts[$i]["timestamp"]);
            $users[$user]["timestamps"][] = $function_date($date,"n/j g:i A");
            $users[$user]["thread_id"][] = $posts[$i]["thread_id"];
            $users[$user]["thread_title"][] = $this->core->getQueries()->getThreadTitle($posts[$i]["thread_id"]);

            
        }
        ksort($users);
        $this->core->getOutput()->renderOutput('forum\ForumThread', 'statPage', $users);
    }

}