(function (wp) {
  const { subscribe, select, dispatch } = wp.data;

  let wasSaving = false;
  let isProcessing = false;

  subscribe(() => {
    if (isProcessing) return;

    const editor = select("core/editor");
    if (!editor) return;

    const isSaving = editor.isSavingPost();

    if (wasSaving && !isSaving) {
      isProcessing = true;

      const meta = editor.getEditedPostAttribute("meta");
      const error = meta ? meta["_auto_translate_error"] : null;

      if (error) {
        dispatch("core/notices").createNotice(
          "error",
          error,
          {
            isDismissible: true,
            type: "snackbar",
          }
        );
      }
      setTimeout(() => {
        isProcessing = false;
      }, 500);
    }

    wasSaving = isSaving;
  });
})(window.wp);
