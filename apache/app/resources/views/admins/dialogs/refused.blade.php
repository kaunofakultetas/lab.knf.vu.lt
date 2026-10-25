{{--
    The answer to a dialog the panel refused (Perms, R_Exists,
    ChallengesController::file_form): a dialog is loaded into the
    modal of the page it was opened from, which would follow a
    redirect and show the whole page it led to inside the modal,
    so the error shows in the modal instead.
--}}
<div class="alert alert-danger mb-0" role="alert">{{ $error }}</div>
