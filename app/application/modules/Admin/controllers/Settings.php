<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Settings extends MX_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('xcrud');
        $result = check_admin();
        if(empty($result))
        {
            redirect(ADMINURL.'login');
        } 
        $this->load->model('Modules_Model', 'mm');
        $this->load->model('Auth_Model', 'am');
        $data = getAllObjects();
        $this->load->vars($data);
    }

    public function index()
    {
        $data['title'] = 'Settings';
        $this->load->library('upload');
        $data['crumbdata'] = array('Settings');
        $data['crumb'] = 'Admin/crumb';
        $this->load->Model('Setting_Model', 'SM');
        $this->load->helper(array('form', 'url'));
        $dir    = FCPATH.'themes';
        $themes_array = array_diff(scandir($dir), array('..', '.'));
        $data["themes"] = $themes_array;
        $post = $this->input->post();
        $image_path = FCPATH.ASSETS_IMG;
        $data["result"] = $this->SM->get_settings();
        if (!empty($post)) {
            $config['upload_path'] = $image_path ;
            $config['allowed_types'] = 'png|jpg|gif';
            $config['max_size'] = 2048;
            $config['overwrite'] = TRUE;
            $config['file_name'] = "favicon";

            $this->upload->initialize($config);
            if (!empty($_FILES["favicon"]["name"])) {
                if (!empty($_FILES['favicon']['name'])) {
                    if (!$this->upload->do_upload('favicon')) {
                        $data['errors_view'] = $this->upload->display_errors();
                        $error = true;
                    } else {
                        $favicon = $this->upload->data("file_name");
                        $post["favicon"] = $favicon;
                    }
                }
            }

            $config['file_name'] = "logo";
            $this->upload->initialize($config);

            if (!empty($_FILES['logo']['name'])) {
                if (!$this->upload->do_upload('logo')) {
                    $errors = $this->upload->display_errors();
                    $data['errors_view'] = $errors;
                    $error = true;
                } else {
                    $logo = $this->upload->data("file_name");
                    $post["logo"] = $logo;
                }
            }
            if (empty($error)) {
                $this->SM->update_settings($post);
                $data["success_view"] = "Update Successfully";
                $data["result"] = $this->SM->get_settings();
            }
            $data['main_content'] = 'Admin/settings';
            $this->load->view('Admin/template', $data);
        } else {
            $data['main_content'] = 'Admin/settings';
            $this->load->view('Admin/template', $data);
        }
    }

    public function modules()
    {
        $data = [];
        $data['title'] = "Modules";
        $modules = $this->mm->get_modules();
        $columns = array_unique(array_column( $modules,'parent'));
        $final_modules = array();
        foreach ($columns as $item){
            $final_modules [$item] = ArrayWhere($modules ,'parent',$item);
        }
        $data["modules"] = $final_modules;
        $data['main_content'] = 'Admin/modules';
        $this->load->view('Admin/template', $data);
    }
    public function update_status()
    {

        echo $this->sm->activiate_modules($this->input->post());
    }
    public function update_modules(){
        $results = json_decode(sendRequest('GET','http://localhost/engine/modules/config.json',array(),''),true);
        $keys = array_keys($results);
        foreach ($keys as $item){
            foreach ($results[$item] as $subItem){
                $object = array(
                    "parent"=>$item,
                    "name"=>$subItem["name"],
                    "active"=>1
                );
                $this->mm->get_module_name($object);

            }
        }
        redirect(base_url('admin/settings/modules'));
    }
    public function module_data(){
        $id = $this->input->get('id');
        $data = $this->mm->get_module_id($id);
        echo json_encode($data);
    }
    public function update_credentials(){
        $module_id = $this->input->post("module_id");
        $credentials = $this->input->post();
        unset($credentials["module_id"]);
        $this->mm->save_credentials($module_id,$credentials);
    }
    public function delete_all()
    {
        echo $this->sm->delete_all_modules($this->input->post('primery_keys'));

    }
    public function delete_all_currencies()
    {
        echo $this->sm->delete_all_currencies($this->input->post('primery_keys'));

    }
    public function delete_all_gateways()
    {
        echo $this->sm->delete_all_gateways($this->input->post('primery_keys'));

    }
    public function delete_all_icons()
    {
        echo $this->sm->delete_all_icons($this->input->post('primery_keys'));

    }

    public function currencies()
    {
        $xcrud = xcrud_get_instance();
        $xcrud->table('global_currencies');
        $xcrud->unset_title();
        $data['title'] = 'Currencies';
        $data['head'] = 'Currencies';
        $data['content'] = $xcrud->render();
        $data['main_content'] = 'Admin/xcrud';
        $data['crumbdata'] = array('Settings','Currencies');
        $data['base_url'] = base_url(ADMINURL.'settings/delete_all_currencies');
        $data['crumb'] = 'Admin/crumb';
        $this->load->view('Admin/template', $data);
    }


    public function payment_gateways()
    {
        $xcrud = xcrud_get_instance();
        $xcrud->table('global_payment_gateways');
        $xcrud->columns('payment_status,payment_name,payment_percentage,payment_show');
        $xcrud->unset_title();
        $data['title'] = 'Payment Gateways';
        $data['head'] = 'Payment Gateways';
        $data['content'] = $xcrud->render();
        $data['main_content'] = 'Admin/xcrud';
        $data['crumbdata'] = array('Settings','Payment Gateways');
        $data['base_url'] = base_url(ADMINURL.'settings/delete_all_gateways');

        $data['crumb'] = 'Admin/crumb';
        $this->load->view('Admin/template', $data);
    }

    public function social()
    {
        $xcrud = xcrud_get_instance();
        $xcrud->table('global_social_icons');
        $xcrud->no_editor('url');
        $xcrud->unset_title();
        $data['title'] = 'Social Icons';
        $data['head'] = 'Social Icons';
        $data['content'] = $xcrud->render();
        $data['base_url'] = base_url(ADMINURL.'settings/delete_all_icons');
        $data['main_content'] = 'Admin/xcrud';
        $data['crumbdata'] = array('Settings','Social');
        $data['crumb'] = 'Admin/crumb';
        $this->load->view('Admin/template', $data);
    }

    /***********update Flights orders********/

            public function updateorder()
    {
        $orderid = $this->input->post('orderid');
        $id = $this->input->post('ids');
        $this->db->set('ordering', $orderid); //value that used to update column  
        $this->db->where('id',$id);
        $this->db->update('modules');
        echo json_encode(['success'=>"order Update successfully."]);
    }
        /***********update Flights status********/

     public function updatestatus()
    {
        $checkbox = $this->input->post('checkbox');
        if ($checkbox == 1) {
           $checkbox = 0;
        }else{
            $checkbox = 1;
        }
        $id = $this->input->post('id');
        $this->db->set('active', $checkbox); //value that used to update column  
        $this->db->where('id',$id);
        $this->db->update('modules');
        echo json_encode(['success'=>"order Update successfully."]);
    }

}