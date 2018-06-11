<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Page uniq training management.
 *
 * Renderable class that is used to render the page that allow user to manage
 * a single training in Attestoodle.
 *
 * @package    block_attestoodle
 * @copyright  2018 Pole de Ressource Numerique de l'Université du Mans
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_attestoodle\output\renderable;

defined('MOODLE_INTERNAL') || die;

use block_attestoodle\factories\categories_factory;
use block_attestoodle\forms\category_training_update_form;

class uniq_training_management implements \renderable {
    /** @var category_training_update_form The form used to manage trainings */
    private $form;

    /** @var integer The category ID that we want to manage */
    private $categoryid = null;

    /** @var category the actual category we want to manage */
    private $category = null;

    /**
     * Constructor method that instanciates the form.
     */
    public function __construct($categoryid) {
        $this->categoryid = $categoryid;

        categories_factory::get_instance()->create_categories_by_ids(array($categoryid));
        $this->category = categories_factory::get_instance()->retrieve_category($categoryid);

        // Handling form is useful only if the category exists.
        if (isset($this->category)) {
            $this->form = new category_training_update_form(
                    new \moodle_url(
                            '/blocks/attestoodle/index.php',
                            array(
                                    'page' => 'uniqtrainingmanagement',
                                    'categoryid' => $this->categoryid
                            )),
                    array(
                            'data' => $this->category,
                            'input_name_prefix' => "attestoodle_category_id_"
                    )
            );

            $this->handle_form();
        }
    }

    /**
     * Main form handling method (calls other actual handling method).
     *
     * @return void Return void if no handling is needed (first render).
     */
    private function handle_form() {
        // Form processing and displaying is done here.
        if ($this->form->is_cancelled()) {
            $this->handle_form_cancelled();
        } else if ($this->form->is_submitted()) {
            $this->handle_form_submitted();
        } else {
            // First render, no process.
            return;
        }
    }

    /**
     * Handles the form cancellation (redirect to trainings list with a message).
     */
    private function handle_form_cancelled() {
        // Handle form cancel operation.
        $redirecturl = new \moodle_url('/blocks/attestoodle/index.php', ['page' => 'trainingslist']);
        $message = get_string('trainings_management_info_form_canceled', 'block_attestoodle');
        redirect($redirecturl, $message, null, \core\output\notification::NOTIFY_INFO);
    }

    /**
     * Handles the form submission (calls other actual form submission handling
     * methods).
     */
    private function handle_form_submitted() {
        // Handle form submit operation.
        // Check the data validity.
        if (!$this->form->is_validated()) {
            $this->handle_form_not_validated();
        } else {
            // If data are valid, process persistance.
            // Try to retrieve the submitted data.
            $this->handle_form_has_submitted_data();
        }
    }

    /**
     * Handle form submission if its not valid (notify an error to the user).
     */
    private function handle_form_not_validated() {
        // If not valid, warn the user.
        \core\notification::error(get_string('trainings_management_warning_invalid_form', 'block_attestoodle'));
    }

    /**
     * Handles form submission if its valid. Return a notification message
     * to the user to let him know how much categories have been updated and if
     * there is any error while save in DB.
     *
     * @todo should "@return void Return void if the user has not the rights to update in DB"
     */
    private function handle_form_has_submitted_data() {
        $datafromform = $this->form->get_submitted_data();
        // Instanciate global variables to output to the user.
        $updatecounter = 0;
        $errorcounter = 0;

        foreach ($datafromform as $key => $value) {
            $matches = [];
            $regexp = "/attestoodle_category_id_(.+)/";
            if (preg_match($regexp, $key, $matches)) {
                $idcategory = $matches[1];
                if (!empty($idcategory) && categories_factory::get_instance()->has_category($idcategory)) {
                    $category = categories_factory::get_instance()->retrieve_category($idcategory);
                    $oldistrainingvalue = $category->is_training();
                    $boolvalue = boolval($value);
                    if ($category->set_istraining($boolvalue)) {
                        try {
                            // Try to persist training in DB.
                            $category->persist_training();

                            // If no Exception has been thrown by DB update.
                            $updatecounter++;
                        } catch (\Exception $ex) {
                            // If record in DB failed, re-set the old value.
                            $category->set_istraining($oldistrainingvalue);
                            $errorcounter++;
                        }
                    }
                }
            }
        }

        $message = "";
        if ($errorcounter == 0) {
            $message .= "Form submitted. <br />"
                    . "{$updatecounter} categories updated <br />";
            \core\notification::success($message);
        } else {
            $message .= "Form submitted with errors. <br />"
                    . "{$updatecounter} categories updated <br />"
                    . "{$errorcounter} errors (categories not updated in database).<br />";
            \core\notification::warning($message);
        }
    }

    /**
     * Computes the content header.
     *
     * @return string The computed HTML string of the page header
     */
    public function get_header() {
        $output = "";

        $output .= \html_writer::start_div('clearfix');
        // Link to the trainings list.
        $output .= \html_writer::link(
                new \moodle_url('/blocks/attestoodle/index.php', ['page' => 'trainingslist']),
                get_string('trainings_management_trainings_list_link', 'block_attestoodle'),
                array('class' => 'attestoodle-link')
        );
        $output .= \html_writer::end_div();

        // Basic form to allow the user to search for a category by its ID.
        // @TODO use a moodle_quickform.
        $output .= '<form action="?"><div>'
                . '<input type="hidden" name="page" value="uniqtrainingmanagement" />';
        $output .= '<label for="input_category_id">'
                . get_string('learner_details_begin_date_label', 'block_attestoodle') . '</label>'
                . '<input type="text" id="input_category_id" name="categoryid" value="' . $this->categoryid . '" />';
        $output .= '<input type="submit" value="'
                . get_string('learner_details_submit_button_value', 'block_attestoodle') . '" />'
                . '</div></form>' . "\n";

        return $output;
    }

    /**
     * Render the form.
     *
     * @return string HTML string corresponding to the form
     */
    public function get_content() {
        if (!isset($this->categoryid)) {
            return "Vous devez spécifier un ID de catégorie à modifier";
        }

        if (!isset($this->category)) {
            return "L'identifiant {$this->categoryid} n'est pas valide.";
        }
//        return "this is the content";
        return $this->form->render();
    }
}
