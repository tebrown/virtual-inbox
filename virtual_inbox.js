function rcmail_vinbox()
{
    if (rcmail.env.uid || rcmail.message_list && rcmail.message_list.get_selection().length)
    {
        var a =
            rcmail.env.uid ? rcmail.env.uid : rcmail.message_list.
        get_selection().join(","),
            b = rcmail.set_busy(!0, "loading");
        rcmail.http_post("plugin.vinboxmail",
            "_uid=" + a + "&_mbox=" +
            urlencode(rcmail.env.mailbox), b)
    }
}

function rcmail_vinbox_contextmenu(a)
{
    (rcmail.env.uid || rcmail.message_list && rcmail.message_list.get_selection().length) && 0 < rcmail.message_list.get_selection().length && rcmail_vinbox(a)
}


$(document).ready(function ()
{
    window.rcmail && ("larry" != rcmail.env.skin && $(".vinboxfolder").text(""),
        rcmail.addEventListener("init", function ()
        {
            rcmail.env.vinbox_folder && rcmail.add_onload("rcmail_vinbox_init()");
            rcmail.register_command("plugin.vinboxmail", rcmail_vinbox, rcmail.env.uid && rcmail.env.mailbox != rcmail.env.vinbox_folder);
            rcmail.message_list && rcmail.message_list.addEventListener("select", function (a)
            {
                rcmail.enable_command("plugin.vinboxmail", 0 < a.get_selection().length && rcmail.env.mailbox != rcmail.env.vinbox_folder)
            });
            rcmail_vinbox_icon()
        }))
});

function rcmail_vinbox_icon()
{
    var a;
    if (rcmail.env.vinbox_folder && rcmail.env.vinbox_folder_icon && (a = rcmail.get_folder_li(rcmail.env.vinbox_folder, "", !0)))
        "larry" != rcmail.env.skin ? $(a).css("background-image",
            "url(" +
            rcmail.env.vinbox_folder_icon +
            ")") : $(a).addClass("vinbox"),
    $(a).insertAfter("#mailboxlist .inbox"), a =
        $("._vinbox"), $(a.get(0)).insertBefore("#rcmContextMenu .drafts")
}

function rcmail_vinbox_init()
{
    window.rcm_contextmenu_register_command && rcm_contextmenu_register_command("vinbox",
        "rcmail_vinbox_contextmenu",
        rcmail.gettext("vinboxfolder.buttontitle"),
        "delete", null, !0)
};
