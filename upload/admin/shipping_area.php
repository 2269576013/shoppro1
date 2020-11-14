<?php

/**
 * ECSHOP ���������������
 * ============================================================================
 * ��Ȩ���� 2005-2009 �Ϻ���������Ƽ����޹�˾������������Ȩ����
 * ��վ��ַ: http://www.ecshop.com��
 * ----------------------------------------------------------------------------
 * �ⲻ��һ��������������ֻ���ڲ�������ҵĿ�ĵ�ǰ���¶Գ����������޸ĺ�
 * ʹ�ã��������Գ���������κ���ʽ�κ�Ŀ�ĵ��ٷ�����
 * ============================================================================
 * $Author: liubo $
 * $Id: shipping_area.php 16881 2009-12-14 09:19:16Z liubo $
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');
$exc = new exchange($ecs->table('shipping_area'), $db, 'shipping_area_id', 'shipping_area_name');

/*------------------------------------------------------ */
//-- ���������б�
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'list')
{
    $shipping_id = intval($_REQUEST['shipping']);

    $list = get_shipping_area_list($shipping_id);
    $smarty->assign('areas',    $list);

    $smarty->assign('ur_here',  '<a href="shipping.php?act=list">'.
        $_LANG['03_shipping_list'].'</a> - ' . $_LANG['shipping_area_list'] . '</a>');
    $smarty->assign('action_link', array('href'=>'shipping_area.php?act=add&shipping='.$shipping_id,
        'text' => $_LANG['new_area']));
    $smarty->assign('full_page', 1);

    assign_query_info();
    $smarty->display('shipping_area_list.htm');
}

/*------------------------------------------------------ */
//-- �½���������
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'add' && !empty($_REQUEST['shipping']))
{
    admin_priv('shiparea_manage');

    $shipping = $db->getRow("SELECT shipping_name, shipping_code FROM " .$ecs->table('shipping'). " WHERE shipping_id='$_REQUEST[shipping]'");

    $set_modules = 1;
    include_once(ROOT_PATH.'includes/modules/shipping/'.$shipping['shipping_code'].'.php');

    $fields = array();
    foreach ($modules[0]['configure'] AS $key => $val)
    {
        $fields[$key]['name']   = $val['name'];
        $fields[$key]['value']  = $val['value'];
        $fields[$key]['label']  = $_LANG[$val['name']];
    }
    $count = count($fields);
    $fields[$count]['name']     = "free_money";
    $fields[$count]['value']    = "0";
    $fields[$count]['label']    = $_LANG["free_money"];

    /* ���֧�ֻ���������������û�������֧������ */
    if ($modules[0]['cod'])
    {
        $count++;
        $fields[$count]['name']     = "pay_fee";
        $fields[$count]['value']    = "0";
        $fields[$count]['label']    = $_LANG['pay_fee'];
    }

    $shipping_area['shipping_id']   = 0;
    $shipping_area['free_money']    = 0;

    $smarty->assign('ur_here',          $shipping['shipping_name'] .' - '. $_LANG['new_area']);
    $smarty->assign('shipping_area',    array('shipping_id' => $_REQUEST['shipping'], 'shipping_code' => $shipping['shipping_code']));
    $smarty->assign('fields',           $fields);
    $smarty->assign('form_action',      'insert');
    $smarty->assign('countries',        get_regions());
    $smarty->assign('default_country',  $_CFG['shop_country']);
    assign_query_info();
    $smarty->display('shipping_area_info.htm');
}

elseif ($_REQUEST['act'] == 'insert')
{
    admin_priv('shiparea_manage');

    /* ���ͬ���͵����ͷ�ʽ����û���������������� */
    $sql = "SELECT COUNT(*) FROM " .$ecs->table("shipping_area").
            " WHERE shipping_id='$_POST[shipping]' AND shipping_area_name='$_POST[shipping_area_name]'";
    if ($db->getOne($sql) > 0)
    {
        sys_msg($_LANG['repeat_area_name'], 1);
    }
    else
    {
        $shipping_code = $db->getOne("SELECT shipping_code FROM " .$ecs->table('shipping').
                                    " WHERE shipping_id='$_POST[shipping]'");
        $plugin        = '../includes/modules/shipping/'. $shipping_code. ".php";

        if (!file_exists($plugin))
        {
            sys_msg($_LANG['not_find_plugin'], 1);
        }
        else
        {
            $set_modules = 1;
            include_once($plugin);
        }

        $config = array();
        foreach ($modules[0]['configure'] AS $key => $val)
        {
            $config[$key]['name']   = $val['name'];
            $config[$key]['value']  = $_POST[$val['name']];
        }

        $count = count($config);
        $config[$count]['name']     = 'free_money';
        $config[$count]['value']    = $_POST['free_money'];
        $count++;
        $config[$count]['name']     = 'fee_compute_mode';
        $config[$count]['value']    = $_POST['fee_compute_mode'];
        /* ���֧�ֻ���������������û�������֧������ */
        if ($modules[0]['cod'])
        {
            $count++;
            $config[$count]['name']     = 'pay_fee';
            $config[$count]['value']    = make_semiangle($_POST['pay_fee']);
        }

        $sql = "INSERT INTO " .$ecs->table('shipping_area').
                " (shipping_area_name, shipping_id, configure) ".
                "VALUES".
                " ('$_POST[shipping_area_name]', '$_POST[shipping]', '" .serialize($config). "')";

        $db->query($sql);

        $new_id = $db->insert_Id();

        /* ����ѡ���ĳ��к͵��� */
        if (isset($_POST['regions']) && is_array($_POST['regions']))
        {
            foreach ($_POST['regions'] AS $key => $val)
            {
                $sql = "INSERT INTO ".$ecs->table('area_region')." (shipping_area_id, region_id) VALUES ('$new_id', '$val')";
                $db->query($sql);
            }
        }

        admin_log($_POST['shipping_area_name'], 'add', 'shipping_area');

        //$lnk[] = array('text' => $_LANG['add_area_region'], 'href'=>'shipping_area.php?act=region&id='.$new_id);
        $lnk[] = array('text' => $_LANG['back_list'], 'href'=>'shipping_area.php?act=list&shipping='.$_POST['shipping']);
        $lnk[] = array('text' => $_LANG['add_continue'], 'href'=>'shipping_area.php?act=add&shipping='.$_POST['shipping']);
        sys_msg($_LANG['add_area_success'], 0, $lnk);
    }
}

/*------------------------------------------------------ */
//-- �༭��������
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'edit')
{
    admin_priv('shiparea_manage');

    $sql = "SELECT a.shipping_name, a.shipping_code, a.support_cod, b.* ".
            "FROM " .$ecs->table('shipping'). " AS a, " .$ecs->table('shipping_area'). " AS b ".
            "WHERE b.shipping_id=a.shipping_id AND b.shipping_area_id='$_REQUEST[id]'";
    $row = $db->getRow($sql);

    $set_modules = 1;
    include_once(ROOT_PATH.'includes/modules/shipping/'.$row['shipping_code'].'.php');

    $fields = unserialize($row['configure']);
    /* ������ͷ�ʽ֧�ֻ��������û�����û�������֧�����ã���������������� */
    if ($row['support_cod'] && $fields[count($fields)-1]['name'] != 'pay_fee')
    {
        $fields[] = array('name'=>'pay_fee', 'value'=>0);
    }

    foreach ($fields AS $key => $val)
    {
       /* �滻���ĵ������� */
       if ($val['name'] == 'basic_fee')
       {
            $val['name'] = 'base_fee';
       }
//       if ($val['name'] == 'step_fee1')
//       {
//            $val['name'] = 'step_fee';
//       }
//       if ($val['name'] == 'step_fee2')
//       {
//            $val['name'] = 'step_fee1';
//       }

       if ($val['name'] == 'item_fee')
       {
           $item_fee = 1;
       }
       if ($val['name'] == 'fee_compute_mode')
       {
           $smarty->assign('fee_compute_mode',$val['value']);
           unset($fields[$key]);
       }
       else
       {
           $fields[$key]['name'] = $val['name'];
           $fields[$key]['label']  = $_LANG[$val['name']];
       }
    }

    if(!$item_fee)
    {
        $field = array('name'=>'item_fee', 'value'=>'0', 'label'=>$_LANG['item_fee']);
        array_unshift($fields,$field);
    }

    /* ��ø������µ����е��� */
    $regions = array();

    $sql = "SELECT a.region_id, r.region_name ".
            "FROM ".$ecs->table('area_region')." AS a, ".$ecs->table('region'). " AS r ".
            "WHERE r.region_id=a.region_id AND a.shipping_area_id='$_REQUEST[id]'";
    $res = $db->query($sql);
    while ($arr = $db->fetchRow($res))
    {
        $regions[$arr['region_id']] = $arr['region_name'];
    }

    assign_query_info();
    $smarty->assign('ur_here',          $row['shipping_name'] .' - '. $_LANG['edit_area']);
    $smarty->assign('id',               $_REQUEST['id']);
    $smarty->assign('fields',           $fields);
    $smarty->assign('shipping_area',    $row);
    $smarty->assign('regions',          $regions);
    $smarty->assign('form_action',      'update');
    $smarty->assign('countries',        get_regions());
    $smarty->assign('default_country',  1);
    $smarty->display('shipping_area_info.htm');
}

elseif ($_REQUEST['act'] == 'update')
{
    admin_priv('shiparea_manage');

    /* ���ͬ���͵����ͷ�ʽ����û���������������� */
    $sql = "SELECT COUNT(*) FROM " .$ecs->table("shipping_area").
            " WHERE shipping_id='$_POST[shipping]' AND ".
                    "shipping_area_name='$_POST[shipping_area_name]' AND ".
                    "shipping_area_id<>'$_POST[id]'";
    if ($db->getOne($sql) > 0)
    {
        sys_msg($_LANG['repeat_area_name'], 1);
    }
    else
    {
        $shipping_code = $db->getOne("SELECT shipping_code FROM " .$ecs->table('shipping'). " WHERE shipping_id='$_POST[shipping]'");
        $plugin        = '../includes/modules/shipping/'. $shipping_code. ".php";

        if (!file_exists($plugin))
        {
            sys_msg($_LANG['not_find_plugin'], 1);
        }
        else
        {
            $set_modules = 1;
            include_once($plugin);
        }

        $config = array();
        foreach ($modules[0]['configure'] AS $key => $val)
        {
            $config[$key]['name']   = $val['name'];
            $config[$key]['value']  = $_POST[$val['name']];
        }

        $count = count($config);
        $config[$count]['name']     = 'free_money';
        $config[$count]['value']    = $_POST['free_money'];
        $count++;
        $config[$count]['name']     = 'fee_compute_mode';
        $config[$count]['value']    = $_POST['fee_compute_mode'];
        if ($modules[0]['cod'])
        {
            $count++;
            $config[$count]['name']     = 'pay_fee';
            $config[$count]['value']    =  make_semiangle($_POST['pay_fee']);
        }

        $sql = "UPDATE " .$ecs->table('shipping_area').
                " SET shipping_area_name='$_POST[shipping_area_name]', ".
                    "configure='" .serialize($config). "' ".
                "WHERE shipping_area_id='$_POST[id]'";

        $db->query($sql);

        admin_log($_POST['shipping_area_name'], 'edit', 'shipping_area');

        /* ���˵��ظ���region */
        $selected_regions = array();
        if (isset($_POST['regions']))
        {
            foreach ($_POST['regions'] AS $region_id)
            {
                $selected_regions[$region_id] = $region_id;
            }
        }

        // ��ѯ�������� region_id => parent_id
        $sql = "SELECT region_id, parent_id FROM " . $ecs->table('region');
        $res = $db->query($sql);
        while ($row = $db->fetchRow($res))
        {
            $region_list[$row['region_id']] = $row['parent_id'];
        }

        // ���˵��ϼ����ڵ�����
        foreach ($selected_regions AS $region_id)
        {
            $id = $region_id;
            while ($region_list[$id] != 0)
            {
                $id = $region_list[$id];
                if (isset($selected_regions[$id]))
                {
                    unset($selected_regions[$region_id]);
                    break;
                }
            }
        }

        /* ���ԭ�еĳ��к͵��� */
        $db->query("DELETE FROM ".$ecs->table("area_region")." WHERE shipping_area_id='$_POST[id]'");

        /* ����ѡ���ĳ��к͵��� */
        foreach ($selected_regions AS $key => $val)
        {
            $sql = "INSERT INTO ".$ecs->table('area_region')." (shipping_area_id, region_id) VALUES ('$_POST[id]', '$val')";
            $db->query($sql);
        }

        $lnk[] = array('text' => $_LANG['back_list'], 'href'=>'shipping_area.php?act=list&shipping='.$_POST['shipping']);

        sys_msg($_LANG['edit_area_success'], 0, $lnk);
    }
}

/*------------------------------------------------------ */
//-- ����ɾ����������
/*------------------------------------------------------ */
elseif ($_REQUEST['act'] == 'multi_remove')
{
    admin_priv('shiparea_manage');

    if (isset($_POST['areas']) && count($_POST['areas']) > 0)
    {
        $i = 0;
        foreach ($_POST['areas'] AS $v)
        {
            $db->query("DELETE FROM " .$ecs->table('shipping_area'). " WHERE shipping_area_id='$v'");
            $i++;
        }

        /* ��¼����Ա���� */
        admin_log('', 'batch_remove', 'shipping_area');
    }
    /* ���� */
    $links[0] = array('href'=>'shipping_area.php?act=list&shipping=' . intval($_REQUEST['shipping']), 'text' => $_LANG['go_back']);
    sys_msg($_LANG['remove_success'], 0, $links);
}

/*------------------------------------------------------ */
//-- �༭������������
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'edit_area')
{
    /* ���Ȩ�� */
    check_authz_json('shiparea_manage');

    /* ȡ�ò��� */
    $id  = intval($_POST['id']);
    $val = json_str_iconv(trim($_POST['val']));

    /* ȡ�ø���������������id */
    $shipping_id = $exc->get_name($id, 'shipping_id');

    /* ����Ƿ����ظ��������������� */
    if (!$exc->is_only('shipping_area_name', $val, $id, "shipping_id = '$shipping_id'"))
    {
        make_json_error($_LANG['repeat_area_name']);
    }

    /* �������� */
    $exc->edit("shipping_area_name = '$val'", $id);

    /* ��¼��־ */
    admin_log($val, 'edit', 'shipping_area');

    /* ���� */
    make_json_result(stripcslashes($val));
}

/*------------------------------------------------------ */
//-- ɾ����������
/*------------------------------------------------------ */

elseif ($_REQUEST['act'] == 'remove_area')
{
    check_authz_json('shiparea_manage');

    $id = intval($_GET['id']);
    $name = $exc->get_name($id);
    $shipping_id = $exc->get_name($id, 'shipping_id');

    $exc->drop($id);
    $db->query('DELETE FROM '.$ecs->table('area_region').' WHERE shipping_area_id='.$id);

    admin_log($name, 'remove', 'shipping_area');

    $list = get_shipping_area_list($shipping_id);
    $smarty->assign('areas', $list);
    make_json_result($smarty->fetch('shipping_area_list.htm'));
}

/**
 * ȡ�����������б�
 * @param   int     $shipping_id    ����id
 */
function get_shipping_area_list($shipping_id)
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('shipping_area');
    if ($shipping_id > 0)
    {
        $sql .= " WHERE shipping_id = '$shipping_id'";
    }
    $res = $GLOBALS['db']->query($sql);
    $list = array();
    while ($row = $GLOBALS['db']->fetchRow($res))
    {
        $sql = "SELECT r.region_name " .
                "FROM " . $GLOBALS['ecs']->table('area_region'). " AS a, " .
                    $GLOBALS['ecs']->table('region') . " AS r ".
                "WHERE a.region_id = r.region_id ".
                "AND a.shipping_area_id = '$row[shipping_area_id]'";
        $regions = join(', ', $GLOBALS['db']->getCol($sql));

        $row['shipping_area_regions'] = empty($regions) ?
            '<a href="shipping_area.php?act=region&amp;id=' .$row['shipping_area_id'].
            '" style="color:red">' .$GLOBALS['_LANG']['empty_regions']. '</a>': $regions;
        $list[] = $row;
    }

    return $list;
}

?>