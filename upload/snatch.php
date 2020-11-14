<?php

/**
 * ECSHOP �ᱦ���ǰ̨ҳ��
 * ============================================================================
 * ��Ȩ���� 2005-2009 �Ϻ���������Ƽ����޹�˾������������Ȩ����
 * ��վ��ַ: http://www.ecshop.com��
 * ----------------------------------------------------------------------------
 * �ⲻ��һ��������������ֻ���ڲ�������ҵĿ�ĵ�ǰ���¶Գ����������޸ĺ�
 * ʹ�ã��������Գ���������κ���ʽ�κ�Ŀ�ĵ��ٷ�����
 * ============================================================================
 * $Author: liubo $
 * $Id: snatch.php 16881 2009-12-14 09:19:16Z liubo $
*/

define('IN_ECS', true);

require(dirname(__FILE__) . '/includes/init.php');

/*------------------------------------------------------ */
//-- �����û��ָ���id����ҳ���ض��򵽼��������Ļ
/*------------------------------------------------------ */
if (empty($_REQUEST['act']))
{
    //Ĭ����ʾҳ��
    $_REQUEST['act'] = 'main';
}

/* ���û��SESSION */
if (empty($_REQUEST['id']))
{
    $id = get_last_snatch();
    if ($id)
    {
        $page = build_uri('snatch', array('sid'=>$id));
        ecs_header("Location: $page\n");
        exit;
    }
    else
    {
        /* ��ǰû���κο�Ĭ�ϵĻ */
        $id = 0;
    }
}
else
{
   $id = intval($_REQUEST['id']);
}

/* ��ʾҳ�沿�� */
if ($_REQUEST['act'] == 'main')
{
    $goods = get_snatch($id);
    if ($goods)
    {
        $position = assign_ur_here(0,$goods['snatch_name']);
        $myprice = get_myprice($id);
        if ($goods['is_end'])
        {
            //�����Ѿ�����,��ȡ����
            $smarty->assign('result',  get_snatch_result($id));
        }
        $smarty->assign('id',          $id);
        $smarty->assign('snatch_goods',       $goods); // ������Ʒ
        $smarty->assign('myprice',     get_myprice($id));
    }
    else
    {
        show_message($_LANG['now_not_snatch']);
    }

    /* ���� */
    $vote = get_vote();
    if (!empty($vote))
    {
        $smarty->assign('vote_id', $vote['id']);
        $smarty->assign('vote',    $vote['content']);
    }

    assign_template();
    assign_dynamic('snatch');
    $smarty->assign('page_title',  $position['title']);
    $smarty->assign('ur_here',     $position['ur_here']);
    $smarty->assign('categories',  get_categories_tree()); // ������
    $smarty->assign('helps',       get_shop_help());       // �������
    $smarty->assign('snatch_list', get_snatch_list());     //������Ч�Ķᱦ����б�
    $smarty->assign('price_list',  get_price_list($id));
    $smarty->assign('promotion_info', get_promotion_info());
    $smarty->assign('feed_url',         ($_CFG['rewrite'] == 1) ? "feed-typesnatch.xml" : 'feed.php?type=snatch'); // RSS URL
    $smarty->display('snatch.dwt');

    exit;
}

/* ���³����б� */
if ($_REQUEST['act'] == 'new_price_list')
{
    $smarty->assign('price_list',  get_price_list($id));
    $smarty->display('library/snatch_price.lbi');

    exit;
}

/* �û����۴��� */
if ($_REQUEST['act'] == 'bid')
{
    include_once(ROOT_PATH .'includes/cls_json.php');
    $json = new JSON();
    $result = array('error'=>0, 'content'=>'');

    $price = isset($_POST['price']) ? floatval($_POST['price']) : 0;
    $price = round($price, 2);

    /* �����Ƿ��½ */
    if (empty($_SESSION['user_id']))
    {
        $result['error'] = 1;
        $result['content'] = $_LANG['not_login'];
        die($json->encode($result));
    }

    /* ��ȡ�������Ϣ����У�� */
    $sql = 'SELECT act_name AS snatch_name, end_time, ext_info FROM ' . $GLOBALS['ecs']->table('goods_activity') . " WHERE act_id ='$id'";
    $row = $db->getRow($sql, 'SILENT');

    if ($row)
    {
        $info = unserialize($row['ext_info']);
        if ($info)
        {
            foreach ($info as $key => $val)
            {
                $row[$key] = $val;
            }
        }
    }

    if (empty($row))
    {
        $result['error'] = 1;
        $result['content'] = $db->error();
        die($json->encode($result));
    }

    if ($row['end_time']< gmtime() )
    {
        $result['error'] = 1;
        $result['content'] = $_LANG['snatch_is_end'];
        die($json->encode($result));
    }

    /* �������Ƿ���� */
    if ($price < $row['start_price'] || $price > $row['end_price'])
    {
        $result['error'] = 1;
        $result['content'] = sprintf($GLOBALS['_LANG']['not_in_range'],$row['start_price'], $row['end_price']);
        die($json->encode($result));
    }

    /* ����û��Ƿ��Ѿ���ͬһ�۸� */
    $sql = 'SELECT COUNT(*) FROM '.$GLOBALS['ecs']->table('snatch_log'). " WHERE snatch_id = '$id' AND user_id = '$_SESSION[user_id]' AND bid_price = '$price'";
    if ($GLOBALS['db']->getOne($sql) > 0)
    {
        $result['error'] = 1;
        $result['content'] = sprintf($GLOBALS['_LANG']['also_bid'], price_format($price, false));
        die($json->encode($result));
    }

    /* ����û������Ƿ��㹻 */
    $sql = 'SELECT pay_points FROM ' .$ecs->table('users'). " WHERE user_id = '" . $_SESSION['user_id']. "'";
    $pay_points = $db->getOne($sql);
    if ($row['cost_points'] > $pay_points)
    {
        $result['error'] = 1;
        $result['content'] = $_LANG['lack_pay_points'];
        die($json->encode($result));
    }

    log_account_change($_SESSION['user_id'], 0, 0, 0, 0-$row['cost_points'],sprintf($_LANG['snatch_log'], $row['snatch_name'])); //�۳��û�����
    $sql = 'INSERT INTO ' .$ecs->table('snatch_log'). '(snatch_id, user_id, bid_price, bid_time) VALUES'.
           "('$id', '" .$_SESSION['user_id']. "', '" .$price."', " .gmtime(). ")";
    $db->query($sql);

    $smarty->assign('myprice',  get_myprice($id));
    $smarty->assign('id',       $id);
    $result['content'] = $smarty->fetch('library/snatch.lbi');
    die($json->encode($result));
}

/*------------------------------------------------------ */
//-- ������Ʒ
/*------------------------------------------------------ */
if ($_REQUEST['act'] == 'buy')
{
    if (empty($id))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    if (empty($_SESSION['user_id']))
    {
        show_message($_LANG['not_login']);
    }

    $snatch = get_snatch($id);


    if (empty($snatch))
    {
        ecs_header("Location: ./\n");
        exit;
    }

    /* δ���������ܹ��� */
    if (empty($snatch['is_end']))
    {
        $page = build_uri('snatch', array('sid'=>$id));
        ecs_header("Location: $page\n");
        exit;
    }

    $result = get_snatch_result($id);

    if ($_SESSION['user_id'] != $result['user_id'])
    {
        show_message($_LANG['not_for_you']);
    }

    //����Ƿ��Ѿ������
    if ($result['order_count'] > 0)
    {
        show_message($_LANG['order_placed']);
    }

    /* ��չ��ﳵ��������Ʒ */
    include_once(ROOT_PATH . 'includes/lib_order.php');
    clear_cart(CART_SNATCH_GOODS);

    /* ���빺�ﳵ */
    $cart = array(
        'user_id'        => $_SESSION['user_id'],
        'session_id'     => SESS_ID,
        'goods_id'       => $snatch['goods_id'],
        'goods_sn'       => addslashes($snatch['goods_sn']),
        'goods_name'     => addslashes($snatch['goods_name']),
        'market_price'   => $snatch['market_price'],
        'goods_price'    => $result['buy_price'],
        'goods_number'   => 1,
        'goods_attr'     => '',
        'is_real'        => $snatch['is_real'],
        'extension_code' => addslashes($snatch['extension_code']),
        'parent_id'      => 0,
        'rec_type'       => CART_SNATCH_GOODS,
        'is_gift'        => 0
    );

    $db->autoExecute($ecs->table('cart'), $cart, 'INSERT');

    /* ��¼�����������ͣ��ᱦ��� */
    $_SESSION['flow_type'] = CART_SNATCH_GOODS;
    $_SESSION['extension_code'] = 'snatch';
    $_SESSION['extension_id'] = $id;

    /* �����ջ���ҳ�� */
    ecs_header("Location: ./flow.php?step=consignee\n");
    exit;

}

/**
 * ȡ���û��Ե�ǰ����������ļ۸�
 *
 * @access  public
 * @param
 *
 * @return void
 */
function get_myprice($id)
{
    $my_only_price  = array();
    $my_price       = array();
    $pay_points     = 0;
    $bid_price      = array();
    if (!empty($_SESSION['user_id']))
    {
        /* ȡ���û����м۸� */
        $sql = 'SELECT bid_price FROM '.$GLOBALS['ecs']->table('snatch_log'). " WHERE snatch_id = '$id' AND user_id = '$_SESSION[user_id]' ORDER BY bid_time DESC";
        $my_price = $GLOBALS['db']->GetCol($sql);

        if ($my_price)
        {
            /* ȡ���û�Ψһ�۸� */
            $sql = 'SELECT bid_price , count(*) AS num FROM '.$GLOBALS['ecs']->table('snatch_log'). "  WHERE snatch_id ='$id' AND bid_price " . db_create_in(join(',', $my_price)). ' GROUP BY bid_price HAVING num = 1';
            $my_only_price = $GLOBALS['db']->GetCol($sql);
        }

        for ($i = 0, $count = count($my_price); $i < $count; $i++)
        {
            $bid_price[] = array('price' => price_format($my_price[$i], false),
                                 'is_only' => in_array($my_price[$i],$my_only_price)
                                );
        }

        $sql = 'SELECT pay_points FROM '. $GLOBALS['ecs']->table('users')." WHERE user_id = '$_SESSION[user_id]'";
        $pay_points = $GLOBALS['db']->GetOne($sql);
        $pay_points = $pay_points.$GLOBALS['_CFG']['integral_name'];
    }

    /* �����ʱ�� */
    $sql = 'SELECT end_time FROM ' .$GLOBALS['ecs']->table('goods_activity').
           " WHERE act_id = '$id' AND act_type=" . GAT_SNATCH;
    $end_time = $GLOBALS['db']->getOne($sql);
    $my_price = array(
        'pay_points'    => $pay_points,
        'bid_price'     => $bid_price,
        'is_end'        => gmtime() > $end_time
        );

    return $my_price;
}

/**
 * ȡ�õ�ǰ���ǰn������
 *
 * @access  public
 * @param   int  $num  �б�����(ȡǰ5��)
 *
 * @return void
 */
function get_price_list($id, $num = 5)
{
    $sql = 'SELECT t1.log_id, t1.bid_price, t2.user_name FROM '.$GLOBALS['ecs']->table('snatch_log').' AS t1, '.$GLOBALS['ecs']->table('users')." AS t2 WHERE snatch_id = '$id' AND t1.user_id = t2.user_id ORDER BY t1.log_id DESC LIMIT $num";
    $res = $GLOBALS['db']->query($sql);
    $price_list = array();
    while ($row = $GLOBALS['db']->FetchRow($res))
    {
        $price_list[] = array('bid_price'=>price_format($row['bid_price'], false),'user_name'=>$row['user_name']);
    }
    return $price_list;
}

/**
 * ȡ������ļ��λ��
 *
 * @access  public
 * @param
 *
 * @return void
 */
function get_snatch_list($num = 10)
{
    $now = gmtime();
    $sql = 'SELECT act_id AS snatch_id, act_name AS snatch_name, end_time '.
           ' FROM ' . $GLOBALS['ecs']->table('goods_activity').
           " WHERE start_time <= '$now' AND act_type=" . GAT_SNATCH .
           " ORDER BY end_time DESC LIMIT $num";
    $snatch_list = array();
    $overtime = 0;
    $res = $GLOBALS['db']->query($sql);
    while ($row = $GLOBALS['db']->FetchRow($res))
    {
        $overtime = $row['end_time'] > $now ? 0 : 1;
        $snatch_list[] = array(
            'snatch_id' => $row['snatch_id'],
            'snatch_name' => $row['snatch_name'],
            'overtime' => $overtime,
            'url'=>build_uri('snatch', array('sid'=>$row['snatch_id']))
                            );
    }
    return $snatch_list;

}

/**
 * ȡ�õ�ǰ���Ϣ
 *
 * @access  public
 *
 * @return �����
 */
function get_snatch($id)
{
    $sql = "SELECT g.goods_id, g.goods_sn, g.is_real, g.goods_name, g.extension_code, g.market_price, g.shop_price AS org_price, " .
                    "IFNULL(mp.user_price, g.shop_price * '$_SESSION[discount]') AS shop_price, " .
                    "g.promote_price, g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb, " .
                    "ga.act_name AS snatch_name, ga.start_time, ga.end_time, ga.ext_info, ga.act_desc AS `desc` ".
                "FROM " .$GLOBALS['ecs']->table('goods_activity'). " AS ga " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('goods')." AS g " .
                    "ON g.goods_id = ga.goods_id " .
                "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
                    "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                "WHERE ga.act_id = '$id' AND g.is_delete = 0";

    $goods = $GLOBALS['db']->GetRow($sql);

    if ($goods)
    {
        $promote_price          = bargain_price($goods['promote_price'], $goods['promote_start_date'], $goods['promote_end_date']);
        $goods['formated_market_price']  = price_format($goods['market_price']);
        $goods['formated_shop_price']    = price_format($goods['shop_price']);
        $goods['formated_promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
        $goods['goods_thumb']   = get_image_path($goods['goods_id'], $goods['goods_thumb'], true);
        $goods['url']           = build_uri('goods', array('gid'=>$goods['goods_id']), $goods['goods_name']);
        $goods['start_time']    = local_date($GLOBALS['_CFG']['time_format'], $goods['start_time']);

        $info = unserialize($goods['ext_info']);
        if ($info)
        {
            foreach ($info as $key => $val)
            {
                $goods[$key] = $val;
            }
            $goods['is_end'] = gmtime() > $goods['end_time'];
            $goods['formated_start_price'] = price_format($goods['start_price']);
            $goods['formated_end_price'] = price_format($goods['end_price']);
            $goods['formated_max_price'] = price_format($goods['max_price']);
        }
        /* ���������ڸ�ʽ��Ϊ�������α�׼ʱ��ʱ��� */
        $goods['gmt_end_time']  = $goods['end_time'];
        $goods['end_time']      = local_date($GLOBALS['_CFG']['time_format'], $goods['end_time']);
        $goods['snatch_time']   = sprintf($GLOBALS['_LANG']['snatch_start_time'], $goods['start_time'], $goods['end_time']);

        return $goods;
    }
    else
    {
        return false;
    }
}

/**
 * ��ȡ���Ҫ���ڵĻid��û���򷵻� 0
 *
 * @access  public
 * @param
 *
 * @return void
 */
function get_last_snatch()
{
    $now = gmtime();
    $sql = 'SELECT act_id FROM ' . $GLOBALS['ecs']->table('goods_activity').
           " WHERE  start_time < '$now' AND end_time > '$now' AND act_type = " . GAT_SNATCH .
           " ORDER BY end_time ASC LIMIT 1";
    return $GLOBALS['db']->GetOne($sql);
}

?>